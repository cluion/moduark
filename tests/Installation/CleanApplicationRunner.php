<?php

declare(strict_types=1);

namespace Tests\Installation;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Tests\Distribution\PackageArchiveContract;

final class CleanApplicationRunner
{
    private string $packagePath;

    private ?string $packageVersion;

    public function __construct(
        string $packagePath,
        private readonly bool $keep = false,
        ?string $packageVersion = null,
    ) {
        $resolved = realpath($packagePath);

        if ($resolved === false || ! is_file($resolved.'/composer.json')) {
            throw new RuntimeException("Moduark package path [{$packagePath}] is invalid.");
        }

        $this->packagePath = $resolved;
        $this->packageVersion = $packageVersion === null
            ? null
            : self::parsePackageVersion($packageVersion);
    }

    /**
     * @return list<int>
     */
    public static function parseMajors(mixed $value): array
    {
        $value = $value === false ? '12,13' : $value;

        if (! is_string($value) || preg_match('/\A(?:12|13)(?:,(?:12|13))*\z/', $value) !== 1) {
            throw new RuntimeException('The --laravel option must contain 12, 13, or 12,13.');
        }

        $majors = [];

        foreach (explode(',', $value) as $major) {
            $majors[(int) $major] = (int) $major;
        }

        return array_values($majors);
    }

    public static function parsePackageVersion(mixed $value): ?string
    {
        if ($value === false) {
            return null;
        }

        if (
            ! is_string($value)
            || preg_match(
                '/\A(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?\z/',
                $value,
            ) !== 1
        ) {
            throw new RuntimeException(
                'The --package option must be an exact stable or pre-release version.',
            );
        }

        return $value;
    }

    /**
     * @param list<int> $majors
     * @return list<array{major: int, version: string}>
     */
    public function run(array $majors): array
    {
        if ($majors === []) {
            throw new RuntimeException('At least one Laravel major is required.');
        }

        foreach ($majors as $major) {
            if (! in_array($major, [12, 13], true)) {
                throw new RuntimeException("Laravel major [{$major}] is outside the installation matrix.");
            }
        }

        $root = sys_get_temp_dir().'/moduark-installation-'.bin2hex(random_bytes(8));

        if (! mkdir($root, 0755, true)) {
            throw new RuntimeException("Unable to create installation root [{$root}].");
        }

        $environment = getenv();
        $environment['COMPOSER_HOME'] = $root.'/composer-home';
        $environment['COMPOSER_CACHE_DIR'] = $root.'/composer-cache';
        $environment['COMPOSER_NO_INTERACTION'] = '1';
        $environment['COMPOSER_PROCESS_TIMEOUT'] = '900';
        $results = [];

        echo "Clean installation root: {$root}\n";
        echo $this->packageVersion === null
            ? "Package source: current checkout as cluion/moduark:dev-main\n"
            : "Package source: Packagist cluion/moduark:{$this->packageVersion}\n";

        try {
            foreach ($majors as $major) {
                $results[] = $this->runMajor($root, $major, $environment);
            }

            return $results;
        } finally {
            if ($this->keep) {
                echo "Preserved installation root: {$root}\n";
            } else {
                $this->deleteDirectory($root);
                echo "Removed installation root: {$root}\n";
            }
        }
    }

    /**
     * @param array<string, string> $environment
     * @return array{major: int, version: string}
     */
    private function runMajor(string $root, int $major, array $environment): array
    {
        $application = $root.'/laravel-'.$major;

        echo "\n== Laravel {$major} clean application ==\n";
        $this->command([
            'composer',
            'create-project',
            "laravel/laravel:^{$major}.0",
            $application,
            '--no-interaction',
            '--no-progress',
            '--prefer-dist',
        ], $root, $environment);
        $this->assertFileMissing(
            $application.'/config/modules.php',
            'The clean Laravel application unexpectedly contains config/modules.php.',
        );

        $packageConstraint = $this->packageVersion ?? 'dev-main';

        if ($this->packageVersion === null) {
            $repository = json_encode([
                'type' => 'path',
                'url' => $this->packagePath,
                'options' => [
                    'versions' => [
                        'cluion/moduark' => 'dev-main',
                    ],
                ],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

            $this->command([
                'composer',
                'config',
                '--json',
                'repositories.moduark',
                $repository,
            ], $application, $environment);
        }

        $this->command([
            'composer',
            'require',
            'cluion/moduark:'.$packageConstraint,
            '--no-interaction',
            '--no-progress',
            '--prefer-dist',
        ], $application, $environment);
        $this->assertFileMissing(
            $application.'/config/modules.php',
            'Installing Moduark must not publish config/modules.php.',
        );

        if ($this->packageVersion !== null) {
            $this->assertPublishedDistribution($application.'/vendor/cluion/moduark');
        }

        $commands = $this->artisan($application, ['list', '--raw'], $environment);

        foreach (
            [
                'make:module',
                'module:baseline',
                'module:cache',
                'module:check',
                'module:clear',
                'module:graph',
                'module:inspect',
                'module:list',
            ] as $command
        ) {
            $this->assertMatches(
                '/^'.preg_quote($command, '/').'\b/m',
                $commands,
                "Package auto-discovery did not register [{$command}].",
            );
        }

        $versionOutput = $this->artisan($application, ['--version'], $environment);
        if (preg_match('/Laravel Framework ([^\s]+)/', $versionOutput, $versionMatch) !== 1) {
            throw new RuntimeException('Unable to determine the installed Laravel framework version.');
        }

        $version = $versionMatch[1];

        $this->artisan($application, ['make:module', 'User'], $environment);
        $modulePath = $application.'/app/Modules/User/UserModule.php';
        $this->assertFileExists($modulePath, 'make:module did not create UserModule.php.');
        $this->assertOnlyGeneratedModuleFile($application.'/app/Modules/User', $modulePath);

        $list = $this->artisan($application, ['module:list'], $environment);
        $this->assertContains('User', $list, 'module:list did not report the generated User Module.');
        $this->assertContains('| 1', $list, 'module:list did not use the default Level 1 configuration.');

        $inspection = $this->artisan($application, ['module:inspect', 'User'], $environment);
        $this->assertContains('Public API (convention)', $inspection, 'module:inspect omitted the Public API.');
        $this->assertContains('UserModule', $inspection, 'module:inspect omitted the generated Module.');

        $check = $this->artisan($application, ['module:check'], $environment);
        $this->assertContains(
            'Architecture check passed: 6 rules evaluated at Level 1 (Modular).',
            $check,
            'module:check did not complete the default Level 1 rule set.',
        );

        $baseline = $this->artisan($application, ['module:baseline'], $environment);
        $this->assertContains(
            'Created architecture baseline with 0 violations',
            $baseline,
            'module:baseline did not create the initial architecture baseline.',
        );
        $this->assertFileExists(
            $application.'/moduark-baseline.json',
            'module:baseline did not write moduark-baseline.json.',
        );

        $jsonCheck = $this->artisan(
            $application,
            ['module:check', '--format=json'],
            $environment,
        );
        $jsonPayload = json_decode($jsonCheck, true, 512, JSON_THROW_ON_ERROR);

        if (
            ! is_array($jsonPayload)
            || ($jsonPayload['schema_version'] ?? null) !== 1
            || ($jsonPayload['status'] ?? null) !== 'passed'
            || ($jsonPayload['exit_code'] ?? null) !== 0
        ) {
            throw new RuntimeException('module:check JSON output did not report a passing result.');
        }

        $githubCheck = $this->artisan(
            $application,
            ['module:check', '--format=github'],
            $environment,
        );
        $this->assertContains(
            '::notice title=Moduark architecture check::'
                .'Architecture check passed: 6 rules evaluated at Level 1 (Modular).',
            $githubCheck,
            'module:check GitHub output did not report a passing result.',
        );

        $graph = $this->artisan($application, ['module:graph'], $environment);
        $this->assertContains('User -> —', $graph, 'module:graph did not include the generated User Module.');

        $capabilityGraph = $this->artisan(
            $application,
            ['module:graph', '--view=capability'],
            $environment,
        );
        $this->assertContains(
            'User -> —',
            $capabilityGraph,
            'module:graph Capability view did not include the generated User Module.',
        );

        $combinedGraph = $this->artisan(
            $application,
            ['module:graph', '--view=combined'],
            $environment,
        );
        $this->assertContains(
            'User -> —',
            $combinedGraph,
            'module:graph combined view did not include the generated User Module.',
        );

        $moduleCachePath = $application.'/bootstrap/cache/moduark.php';
        $moduleCache = $this->artisan($application, ['module:cache'], $environment);
        $this->assertContains(
            'Module cache created successfully: 1 Module cached.',
            $moduleCache,
            'module:cache did not report the generated User Module.',
        );
        $this->assertFileExists($moduleCachePath, 'module:cache did not create its manifest.');

        $cachedModuleCheck = $this->artisan($application, ['module:check'], $environment);
        $this->assertContains(
            'Architecture check passed: 6 rules evaluated at Level 1 (Modular).',
            $cachedModuleCheck,
            'module:check did not use the Module cache successfully.',
        );

        $this->artisan($application, ['module:clear'], $environment);
        $this->assertFileMissing($moduleCachePath, 'module:clear did not remove its manifest.');

        $this->artisan($application, ['optimize'], $environment);
        $this->assertFileExists(
            $moduleCachePath,
            'Laravel optimize did not create the Module cache manifest.',
        );
        $this->artisan($application, ['optimize:clear'], $environment);
        $this->assertFileMissing(
            $moduleCachePath,
            'Laravel optimize:clear did not remove the Module cache manifest.',
        );

        $this->artisan($application, ['config:cache'], $environment);

        try {
            $cachedCheck = $this->artisan($application, ['module:check'], $environment);
            $this->assertContains(
                'Architecture check passed: 6 rules evaluated at Level 1 (Modular).',
                $cachedCheck,
                'module:check did not survive Laravel configuration caching.',
            );
            $cachedInspection = $this->artisan(
                $application,
                ['module:inspect', 'User'],
                $environment,
            );
            $this->assertContains(
                'UserModule',
                $cachedInspection,
                'module:inspect did not survive Laravel configuration caching.',
            );
        } finally {
            $this->artisan($application, ['config:clear'], $environment);
        }

        echo "PASS Laravel {$major} ({$version})\n";

        return [
            'major' => $major,
            'version' => $version,
        ];
    }

    /**
     * @param list<string> $arguments
     * @param array<string, string> $environment
     */
    private function artisan(string $application, array $arguments, array $environment): string
    {
        return $this->command(
            [PHP_BINARY, 'artisan', ...$arguments, '--no-ansi'],
            $application,
            $environment,
        );
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $environment
     */
    private function command(array $command, string $workingDirectory, array $environment): string
    {
        echo '$ '.$this->commandLabel($command)."\n";
        $errorStream = fopen('php://temp', 'w+');

        if ($errorStream === false) {
            throw new RuntimeException('Unable to open an installation error stream.');
        }

        $process = proc_open(
            $command,
            [
                1 => ['pipe', 'w'],
                2 => $errorStream,
            ],
            $pipes,
            $workingDirectory,
            $environment,
        );

        if (! is_resource($process)) {
            fclose($errorStream);

            throw new RuntimeException('Unable to start installation command.');
        }

        $output = '';

        while (($line = fgets($pipes[1])) !== false) {
            echo $line;
            $output .= $line;
        }

        fclose($pipes[1]);
        $exitCode = proc_close($process);
        rewind($errorStream);
        $errorOutput = stream_get_contents($errorStream);
        fclose($errorStream);

        if ($errorOutput !== false && $errorOutput !== '') {
            echo $errorOutput;
            $output .= $errorOutput;
        }

        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf(
                'Installation command [%s] failed with exit code %d.',
                $this->commandLabel($command),
                $exitCode,
            ));
        }

        return $output;
    }

    /**
     * @param list<string> $command
     */
    private function commandLabel(array $command): string
    {
        return implode(' ', array_map(
            static fn (string $argument): string => preg_match('/\A[A-Za-z0-9_@%+=:,\.\/^-]+\z/', $argument) === 1
                ? $argument
                : escapeshellarg($argument),
            $command,
        ));
    }

    private function assertOnlyGeneratedModuleFile(string $directory, string $expected): void
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        sort($files, SORT_STRING);

        if ($files !== [$expected]) {
            throw new RuntimeException('make:module must generate exactly one Module entry file.');
        }
    }

    private function assertFileExists(string $path, string $message): void
    {
        if (! is_file($path)) {
            throw new RuntimeException($message);
        }
    }

    private function assertFileMissing(string $path, string $message): void
    {
        if (file_exists($path)) {
            throw new RuntimeException($message);
        }
    }

    private function assertPublishedDistribution(string $packageRoot): void
    {
        foreach (PackageArchiveContract::REQUIRED_FILES as $required) {
            $this->assertFileExists(
                $packageRoot.'/'.$required,
                "Published package is missing required file [{$required}].",
            );
        }

        foreach (PackageArchiveContract::EXCLUDED_TREES as $excluded) {
            $this->assertFileMissing(
                $packageRoot.'/'.rtrim($excluded, '/'),
                "Published package contains development tree [{$excluded}].",
            );
        }

        foreach (PackageArchiveContract::EXCLUDED_FILES as $excluded) {
            $this->assertFileMissing(
                $packageRoot.'/'.$excluded,
                "Published package contains development file [{$excluded}].",
            );
        }
    }

    private function assertContains(string $expected, string $actual, string $message): void
    {
        if (! str_contains($actual, $expected)) {
            throw new RuntimeException($message);
        }
    }

    private function assertMatches(string $pattern, string $actual, string $message): void
    {
        if (preg_match($pattern, $actual) !== 1) {
            throw new RuntimeException($message);
        }
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $entry */
        foreach ($iterator as $entry) {
            if ($entry->isLink() || $entry->isFile()) {
                unlink($entry->getPathname());
            } else {
                rmdir($entry->getPathname());
            }
        }

        rmdir($path);
    }
}

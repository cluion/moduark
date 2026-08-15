<?php

declare(strict_types=1);

namespace Tests\Installation;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class CleanApplicationRunner
{
    private string $packagePath;

    public function __construct(string $packagePath, private readonly bool $keep = false)
    {
        $resolved = realpath($packagePath);

        if ($resolved === false || ! is_file($resolved.'/composer.json')) {
            throw new RuntimeException("Moduark package path [{$packagePath}] is invalid.");
        }

        $this->packagePath = $resolved;
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
        $this->command([
            'composer',
            'require',
            'cluion/moduark:dev-main',
            '--no-interaction',
            '--no-progress',
            '--prefer-dist',
        ], $application, $environment);
        $this->assertFileMissing(
            $application.'/config/modules.php',
            'Installing Moduark must not publish config/modules.php.',
        );

        $commands = $this->artisan($application, ['list', '--raw'], $environment);

        foreach (['make:module', 'module:list', 'module:check', 'module:graph'] as $command) {
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

        $check = $this->artisan($application, ['module:check'], $environment);
        $this->assertContains(
            'Architecture check passed: 6 rules evaluated at Level 1 (Modular).',
            $check,
            'module:check did not complete the default Level 1 rule set.',
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

        $this->artisan($application, ['config:cache'], $environment);

        try {
            $cachedCheck = $this->artisan($application, ['module:check'], $environment);
            $this->assertContains(
                'Architecture check passed: 6 rules evaluated at Level 1 (Modular).',
                $cachedCheck,
                'module:check did not survive Laravel configuration caching.',
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

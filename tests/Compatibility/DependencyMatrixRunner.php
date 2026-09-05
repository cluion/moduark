<?php

declare(strict_types=1);

namespace Tests\Compatibility;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class DependencyMatrixRunner
{
    private string $packagePath;

    public function __construct(
        string $packagePath,
        private readonly bool $keep = false,
        private readonly bool $executeTests = false,
    )
    {
        $resolved = realpath($packagePath);

        if ($resolved === false || ! is_file($resolved.'/composer.json')) {
            throw new RuntimeException("Moduark package path [{$packagePath}] is invalid.");
        }

        $this->packagePath = $resolved;
    }

    /**
     * @return array<string, array{
     *     php: string,
     *     laravel: string,
     *     testbench: string,
     *     dependencies: 'lowest'|'highest'
     * }>
     */
    public static function cases(): array
    {
        return [
            'laravel-12-lowest' => [
                'php' => '8.2.0',
                'laravel' => '^12.0',
                'testbench' => '^10.0',
                'dependencies' => 'lowest',
            ],
            'laravel-12-highest' => [
                'php' => '8.5.0',
                'laravel' => '^12.0',
                'testbench' => '^10.0',
                'dependencies' => 'highest',
            ],
            'laravel-13-lowest' => [
                'php' => '8.3.0',
                'laravel' => '^13.0',
                'testbench' => '^11.0',
                'dependencies' => 'lowest',
            ],
            'laravel-13-highest' => [
                'php' => '8.5.0',
                'laravel' => '^13.0',
                'testbench' => '^11.0',
                'dependencies' => 'highest',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function parseCases(mixed $value): array
    {
        $available = array_keys(self::cases());

        if ($value === false) {
            return $available;
        }

        if (! is_string($value) || $value === '') {
            throw new RuntimeException('The --case option must contain a dependency matrix case name.');
        }

        $selected = [];

        foreach (explode(',', $value) as $case) {
            if (! in_array($case, $available, true)) {
                throw new RuntimeException("Dependency matrix case [{$case}] is not supported.");
            }

            $selected[$case] = $case;
        }

        return array_values($selected);
    }

    /**
     * @param list<string> $selected
     * @return list<array{
     *     case: string,
     *     php: string,
     *     laravel: string,
     *     testbench: string,
     *     phpunit: string,
     *     tests_executed: bool,
     *     runtime_php: string
     * }>
     */
    public function run(array $selected): array
    {
        if ($selected === []) {
            throw new RuntimeException('At least one dependency matrix case is required.');
        }

        $cases = self::cases();

        foreach ($selected as $case) {
            if (! isset($cases[$case])) {
                throw new RuntimeException("Dependency matrix case [{$case}] is not supported.");
            }
        }

        $root = sys_get_temp_dir().'/moduark-dependencies-'.bin2hex(random_bytes(8));

        if (! mkdir($root, 0755, true)) {
            throw new RuntimeException("Unable to create dependency matrix root [{$root}].");
        }

        $environment = getenv();
        $environment['COMPOSER_HOME'] = $root.'/composer-home';
        $environment['COMPOSER_CACHE_DIR'] = $root.'/composer-cache';
        $environment['COMPOSER_NO_INTERACTION'] = '1';
        $environment['COMPOSER_PROCESS_TIMEOUT'] = '900';
        $environment['COMPOSER_ROOT_VERSION'] = 'dev-main';
        $results = [];

        echo "Dependency matrix root: {$root}\n";

        try {
            foreach ($selected as $case) {
                $results[] = $this->runCase($root, $case, $cases[$case], $environment);
            }

            return $results;
        } finally {
            if ($this->keep) {
                echo "Preserved dependency matrix root: {$root}\n";
            } else {
                $this->deleteDirectory($root);
                echo "Removed dependency matrix root: {$root}\n";
            }
        }
    }

    /**
     * @param array{
     *     php: string,
     *     laravel: string,
     *     testbench: string,
     *     dependencies: 'lowest'|'highest'
     * } $configuration
     * @param array<string, string> $environment
     * @return array{
     *     case: string,
     *     php: string,
     *     laravel: string,
     *     testbench: string,
     *     phpunit: string,
     *     tests_executed: bool,
     *     runtime_php: string
     * }
     */
    private function runCase(string $root, string $case, array $configuration, array $environment): array
    {
        $directory = $root.'/'.$case;

        if (! mkdir($directory, 0755, true)) {
            throw new RuntimeException("Unable to create dependency case directory [{$directory}].");
        }

        if ($this->executeTests) {
            $this->copyPackage($directory);
        } elseif (! copy($this->packagePath.'/composer.json', $directory.'/composer.json')) {
            throw new RuntimeException("Unable to copy Composer metadata for [{$case}].");
        }

        echo "\n== {$case} ==\n";
        $this->command([
            'composer',
            'config',
            'platform.php',
            $configuration['php'],
            '--no-interaction',
        ], $directory, $environment);
        $this->command([
            'composer',
            'require',
            '--dev',
            "laravel/framework:{$configuration['laravel']}",
            "orchestra/testbench:{$configuration['testbench']}",
            '--no-update',
            '--no-interaction',
            '--no-scripts',
        ], $directory, $environment);

        $update = [
            'composer',
            'update',
            '--prefer-dist',
            '--no-interaction',
            '--no-progress',
        ];

        if (! $this->executeTests) {
            $update[] = '--no-install';
            $update[] = '--no-scripts';
        }

        if ($configuration['dependencies'] === 'lowest') {
            $update[] = '--prefer-lowest';
        }

        $this->command($update, $directory, $environment);

        $result = [
            'case' => $case,
            'php' => $configuration['php'],
            'laravel' => $this->packageVersion($directory.'/composer.lock', 'laravel/framework'),
            'testbench' => $this->packageVersion($directory.'/composer.lock', 'orchestra/testbench'),
            'phpunit' => $this->packageVersion($directory.'/composer.lock', 'phpunit/phpunit'),
            'tests_executed' => $this->executeTests,
            'runtime_php' => PHP_VERSION,
        ];

        if ($this->executeTests) {
            $this->command([
                PHP_BINARY,
                'vendor/bin/phpunit',
                '--testsuite=Architecture,Unit,Feature',
                '--exclude-filter=GenerationBenchmarkTest',
                '--colors=never',
            ], $directory, $environment);
        }

        echo sprintf(
            "PASS %s (PHP %s, Laravel %s, Testbench %s, PHPUnit %s)\n",
            $result['case'],
            $result['php'],
            $result['laravel'],
            $result['testbench'],
            $result['phpunit'],
        );

        return $result;
    }

    private function copyPackage(string $target): void
    {
        $excluded = [
            '.git',
            '.internal',
            '.phpstan',
            '.phpunit.cache',
            'composer.lock',
            'vendor',
        ];

        foreach (new FilesystemIterator($this->packagePath, FilesystemIterator::SKIP_DOTS) as $entry) {
            if (! $entry instanceof \SplFileInfo) {
                throw new RuntimeException('Package copy encountered an unsupported filesystem entry.');
            }

            if (in_array($entry->getFilename(), $excluded, true)) {
                continue;
            }

            $destination = $target.'/'.$entry->getFilename();

            if ($entry->isLink()) {
                throw new RuntimeException("Package copy does not support symlink [{$entry->getPathname()}].");
            }

            if ($entry->isDir()) {
                if (! mkdir($destination, 0755)) {
                    throw new RuntimeException("Unable to create package copy directory [{$destination}].");
                }

                $this->copyDirectory($entry->getPathname(), $destination);
                continue;
            }

            if (! copy($entry->getPathname(), $destination)) {
                throw new RuntimeException("Unable to copy package file [{$entry->getPathname()}].");
            }
        }
    }

    private function copyDirectory(string $source, string $target): void
    {
        foreach (new FilesystemIterator($source, FilesystemIterator::SKIP_DOTS) as $entry) {
            if (! $entry instanceof \SplFileInfo) {
                throw new RuntimeException('Package copy encountered an unsupported filesystem entry.');
            }

            $destination = $target.'/'.$entry->getFilename();

            if ($entry->isLink()) {
                throw new RuntimeException("Package copy does not support symlink [{$entry->getPathname()}].");
            }

            if ($entry->isDir()) {
                if (! mkdir($destination, 0755)) {
                    throw new RuntimeException("Unable to create package copy directory [{$destination}].");
                }

                $this->copyDirectory($entry->getPathname(), $destination);
                continue;
            }

            if (! copy($entry->getPathname(), $destination)) {
                throw new RuntimeException("Unable to copy package file [{$entry->getPathname()}].");
            }
        }
    }

    private function packageVersion(string $lockPath, string $package): string
    {
        $contents = file_get_contents($lockPath);

        if ($contents === false) {
            throw new RuntimeException("Unable to read Composer lock file [{$lockPath}].");
        }

        $lock = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($lock)) {
            throw new RuntimeException("Composer lock file [{$lockPath}] is invalid.");
        }

        foreach (['packages', 'packages-dev'] as $collection) {
            $entries = $lock[$collection] ?? null;

            if (! is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (
                    is_array($entry)
                    && ($entry['name'] ?? null) === $package
                    && is_string($entry['version'] ?? null)
                ) {
                    return $entry['version'];
                }
            }
        }

        throw new RuntimeException("Package [{$package}] is missing from [{$lockPath}].");
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $environment
     */
    private function command(array $command, string $workingDirectory, array $environment): void
    {
        echo '$ '.$this->commandLabel($command)."\n";
        $errorStream = fopen('php://temp', 'w+');

        if ($errorStream === false) {
            throw new RuntimeException('Unable to open a dependency matrix error stream.');
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

            throw new RuntimeException('Unable to start dependency matrix command.');
        }

        while (($line = fgets($pipes[1])) !== false) {
            echo $line;
        }

        fclose($pipes[1]);
        $exitCode = proc_close($process);
        rewind($errorStream);
        $errorOutput = stream_get_contents($errorStream);
        fclose($errorStream);

        if ($errorOutput !== false && $errorOutput !== '') {
            echo $errorOutput;
        }

        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf(
                'Dependency matrix command [%s] failed with exit code %d.',
                $this->commandLabel($command),
                $exitCode,
            ));
        }
    }

    /**
     * @param list<string> $command
     */
    private function commandLabel(array $command): string
    {
        return implode(' ', array_map(
            static fn (string $argument): string => preg_match('/\A[A-Za-z0-9_@%+=:,.\/^-]+\z/', $argument) === 1
                ? $argument
                : escapeshellarg($argument),
            $command,
        ));
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

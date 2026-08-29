<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Export\ModuleExportFilesystem;
use Cluion\Moduark\Export\NativeModuleExportFilesystem;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use JsonException;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

final class ModuleExportCommandTest extends TestCase
{
    /** @throws JsonException */
    public function test_standalone_export_plan_is_deterministic_and_writes_nothing(): void
    {
        $target = 'packages/moduark-user-plan';
        $absolute = base_path($target);
        self::assertFileDoesNotExist($absolute);
        self::assertDirectoryDoesNotExist($absolute);

        [$firstExit, $first, $firstJson] = $this->jsonOutput('User', $target);
        [$secondExit, $second, $secondJson] = $this->jsonOutput('User', $target);

        self::assertSame(ExitPolicy::SUCCESS, $firstExit);
        self::assertSame($firstExit, $secondExit);
        self::assertSame($firstJson, $secondJson);
        self::assertSame($first, $second);
        self::assertSame('planned', $first['status']);
        self::assertTrue($first['complete']);
        self::assertTrue($first['dry_run']);
        self::assertIsArray($first['summary']);
        self::assertIsArray($first['files']);
        self::assertIsArray($first['dependencies']);
        self::assertSame([], $first['blockers']);
        self::assertSame(0, $first['summary']['manual_dependencies']);
        self::assertSame([
            'composer.json',
            'src/UserModule.php',
            'src/UserPackageServiceProvider.php',
        ], array_column($first['files'], 'destination'));
        self::assertSame([
            'cluion/moduark',
            'illuminate/support',
        ], array_column($first['dependencies'], 'source'));
        self::assertSame(ExitPolicy::SUCCESS, $first['exit_code']);
        self::assertNull($first['error']);
        self::assertFileDoesNotExist($absolute);
        self::assertDirectoryDoesNotExist($absolute);
    }

    /** @throws JsonException */
    public function test_export_requires_explicit_package_identity(): void
    {
        [$missingOptionsExit, $missingOptions] = $this->rawJsonOutput([
            'module' => 'User',
            '--format' => 'json',
        ]);

        self::assertSame(ExitPolicy::TOOL_ERROR, $missingOptionsExit);
        self::assertSame('error', $missingOptions['status']);
        self::assertFalse($missingOptions['dry_run']);
        self::assertIsString($missingOptions['error']);
        self::assertStringContainsString('--target, --package, and --namespace', $missingOptions['error']);
    }

    /** @throws JsonException */
    public function test_export_materializes_the_exact_plan_atomically(): void
    {
        $target = 'packages/moduark-user-materialized';
        $absolute = base_path($target);
        $filesystem = new Filesystem;
        self::assertDirectoryDoesNotExist($absolute);
        [, $planned] = $this->jsonOutput('User', $target);

        try {
            [$exitCode, $exported] = $this->jsonOutput('User', $target, false);

            self::assertSame(ExitPolicy::SUCCESS, $exitCode);
            self::assertSame('exported', $exported['status']);
            self::assertTrue($exported['complete']);
            self::assertFalse($exported['dry_run']);
            self::assertSame([], $exported['rollback_failures']);
            self::assertSame($planned['files'], $exported['files']);
            self::assertDirectoryExists($absolute);
            self::assertSame([
                'composer.json',
                'src/UserModule.php',
                'src/UserPackageServiceProvider.php',
            ], $this->relativeFiles($filesystem, $absolute));
            self::assertStringContainsString(
                'namespace Acme\\UserModule;',
                $filesystem->get($absolute.'/src/UserModule.php'),
            );
            $composer = json_decode($filesystem->get($absolute.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($composer);
            self::assertSame('acme/user-module', $composer['name']);
            self::assertSame('proprietary', $composer['license']);
            self::assertIsArray($composer['extra']);
            self::assertIsArray($composer['extra']['laravel']);
            self::assertSame(
                ['Acme\\UserModule\\UserPackageServiceProvider'],
                $composer['extra']['laravel']['providers'],
            );
            self::assertSame([
                'schema_version' => 1,
                'modules' => [[
                    'name' => 'User',
                    'class' => 'Acme\\UserModule\\UserModule',
                    'path' => 'src/UserModule.php',
                ]],
            ], $composer['extra']['moduark']);
        } finally {
            $filesystem->deleteDirectory($absolute);
        }
    }

    /** @throws JsonException */
    public function test_materialization_failure_removes_staging_and_target(): void
    {
        $target = 'moduark-export-failure-parent/package';
        $absolute = base_path($target);
        $parent = dirname($absolute);
        self::assertDirectoryDoesNotExist($parent);
        $this->application()->instance(
            ModuleExportFilesystem::class,
            new FailingModuleExportFilesystem(2),
        );

        [$exitCode, $payload] = $this->jsonOutput('User', $target, false);

        self::assertSame(ExitPolicy::TOOL_ERROR, $exitCode);
        self::assertSame('error', $payload['status']);
        self::assertFalse($payload['complete']);
        self::assertFalse($payload['dry_run']);
        self::assertIsString($payload['error']);
        self::assertStringContainsString('Injected export write failure', $payload['error']);
        self::assertSame([], $payload['rollback_failures']);
        self::assertDirectoryDoesNotExist($absolute);
        self::assertDirectoryDoesNotExist($parent);
    }

    /** @throws JsonException */
    public function test_unresolved_module_dependency_blocks_but_retains_the_plan(): void
    {
        [$exitCode, $payload] = $this->jsonOutput('Order', 'packages/moduark-order-plan');

        self::assertSame(ExitPolicy::VIOLATIONS_FOUND, $exitCode);
        self::assertSame('blocked', $payload['status']);
        self::assertIsArray($payload['blockers']);
        self::assertIsArray($payload['summary']);
        self::assertIsArray($payload['files']);
        self::assertIsArray($payload['dependencies']);
        self::assertContains('MOD-EXPORT-DEPENDENCY-001', array_column($payload['blockers'], 'code'));
        self::assertSame(1, $payload['summary']['manual_dependencies']);
        self::assertNotSame([], $payload['files']);
        $moduleDependencies = array_values(array_filter(
            $payload['dependencies'],
            static fn (mixed $dependency): bool => is_array($dependency)
                && ($dependency['kind'] ?? null) === 'module',
        ));
        self::assertCount(1, $moduleDependencies);
        self::assertSame('manual', $moduleDependencies[0]['status']);
    }

    /** @throws JsonException */
    public function test_existing_destination_file_blocks_without_overwriting_it(): void
    {
        $target = 'packages/moduark-export-collision-fixture';
        $absolute = base_path($target);
        $filesystem = new Filesystem;
        $filesystem->ensureDirectoryExists($absolute);
        $filesystem->put($absolute.'/composer.json', "fixture\n");

        try {
            [$exitCode, $payload] = $this->jsonOutput('User', $target, false);

            self::assertSame(ExitPolicy::VIOLATIONS_FOUND, $exitCode);
            self::assertSame('blocked', $payload['status']);
            self::assertFalse($payload['dry_run']);
            self::assertIsArray($payload['blockers']);
            self::assertContains('MOD-EXPORT-COLLISION-001', array_column($payload['blockers'], 'code'));
            self::assertSame("fixture\n", $filesystem->get($absolute.'/composer.json'));
        } finally {
            $filesystem->deleteDirectory($absolute);
        }
    }

    /** @throws JsonException */
    public function test_source_symlink_blocks_without_following_it(): void
    {
        $moduleRoot = $this->application()->make(ModulesConfig::class)->path().'/User';
        $link = $moduleRoot.'/linked-source.txt';
        self::assertTrue(symlink($moduleRoot.'/UserModule.php', $link));

        try {
            [$exitCode, $payload] = $this->jsonOutput('User', 'packages/moduark-source-link-plan');

            self::assertSame(ExitPolicy::VIOLATIONS_FOUND, $exitCode);
            self::assertSame('blocked', $payload['status']);
            self::assertIsArray($payload['blockers']);
            self::assertContains('MOD-EXPORT-SOURCE-001', array_column($payload['blockers'], 'code'));
            self::assertTrue(is_link($link));
        } finally {
            if (is_link($link)) {
                unlink($link);
            }
        }
    }

    /** @throws JsonException */
    public function test_target_symlink_ancestor_blocks_without_writing_through_it(): void
    {
        $link = base_path('moduark-export-linked-target');
        $destination = base_path('app');
        self::assertTrue(symlink($destination, $link));

        try {
            [$exitCode, $payload] = $this->jsonOutput('User', 'moduark-export-linked-target/package');

            self::assertSame(ExitPolicy::VIOLATIONS_FOUND, $exitCode);
            self::assertSame('blocked', $payload['status']);
            self::assertIsArray($payload['blockers']);
            self::assertContains('MOD-EXPORT-TARGET-001', array_column($payload['blockers'], 'code'));
            self::assertDirectoryDoesNotExist($destination.'/package');
        } finally {
            if (is_link($link)) {
                unlink($link);
            }
        }
    }

    /** @throws JsonException */
    public function test_unsafe_target_is_a_tool_error(): void
    {
        [$exitCode, $payload] = $this->jsonOutput('User', '../outside');

        self::assertSame(ExitPolicy::TOOL_ERROR, $exitCode);
        self::assertSame('error', $payload['status']);
        self::assertSame(
            'The export target must be a portable application-relative path.',
            $payload['error'],
        );
    }

    /**
     * @return array{int, array<string, mixed>, string}
     * @throws JsonException
     */
    private function jsonOutput(string $module, string $target, bool $dryRun = true): array
    {
        return $this->rawJsonOutput([
            'module' => $module,
            '--dry-run' => $dryRun,
            '--target' => $target,
            '--package' => 'acme/'.strtolower($module).'-module',
            '--namespace' => 'Acme\\'.$module.'Module',
            '--format' => 'json',
        ]);
    }

    /**
     * @param array<string, bool|string> $arguments
     * @return array{int, array<string, mixed>, string}
     * @throws JsonException
     */
    private function rawJsonOutput(array $arguments): array
    {
        $output = new BufferedOutput;
        $exitCode = $this->application()->make(Kernel::class)->call(
            'moduark:export',
            $arguments,
            $output,
        );
        $json = trim($output->fetch());
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        $normalized = [];

        foreach ($payload as $key => $value) {
            self::assertIsString($key);
            $normalized[$key] = $value;
        }

        return [$exitCode, $normalized, $json];
    }

    /** @return list<string> */
    private function relativeFiles(Filesystem $filesystem, string $root): array
    {
        $files = array_map(
            static fn ($file): string => str_replace('\\', '/', $file->getRelativePathname()),
            $filesystem->allFiles($root),
        );
        sort($files, SORT_STRING);

        return $files;
    }
}

final class FailingModuleExportFilesystem implements ModuleExportFilesystem
{
    private int $writes = 0;

    private NativeModuleExportFilesystem $native;

    public function __construct(private readonly int $failOnWrite)
    {
        $this->native = new NativeModuleExportFilesystem;
    }

    public function ensureDirectory(string $path): void
    {
        $this->native->ensureDirectory($path);
    }

    public function read(string $path): string
    {
        return $this->native->read($path);
    }

    public function write(string $path, string $contents): void
    {
        $this->writes++;

        if ($this->writes === $this->failOnWrite) {
            throw new \RuntimeException('Injected export write failure.');
        }

        $this->native->write($path, $contents);
    }

    public function moveDirectory(string $source, string $destination): void
    {
        $this->native->moveDirectory($source, $destination);
    }

    public function delete(string $path): void
    {
        $this->native->delete($path);
    }

    public function removeEmptyDirectory(string $path): void
    {
        $this->native->removeEmptyDirectory($path);
    }
}

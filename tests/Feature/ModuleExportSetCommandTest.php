<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Export\ModuleExportFilesystem;
use Cluion\Moduark\Export\NativeModuleExportFilesystem;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use JsonException;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

final class ModuleExportSetCommandTest extends TestCase
{
    /** @throws JsonException */
    public function test_it_builds_a_deterministic_dependency_closed_topological_plan(): void
    {
        $firstArguments = [
            '--package' => [
                'Order=acme/order-module:^1.0=>Acme\OrderModule',
                'user=acme/user-module:^1.0=>Acme\UserModule',
            ],
            '--target' => [
                'Order=packages/set-order-module',
                'User=packages/set-user-module',
            ],
            '--format' => 'json',
        ];
        $secondArguments = [
            '--package' => array_reverse($firstArguments['--package']),
            '--target' => array_reverse($firstArguments['--target']),
            '--format' => 'json',
        ];

        [$firstExit, $first, $firstJson] = $this->jsonOutput($firstArguments);
        [$secondExit, $second, $secondJson] = $this->jsonOutput($secondArguments);

        self::assertSame(ExitPolicy::SUCCESS, $firstExit);
        self::assertSame($firstExit, $secondExit);
        self::assertSame($firstJson, $secondJson);
        self::assertSame($first, $second);
        self::assertSame(1, $first['schema_version']);
        self::assertSame('planned', $first['status']);
        self::assertTrue($first['complete']);
        self::assertTrue($first['dry_run']);
        self::assertSame(['User', 'Order'], $first['order']);
        self::assertSame([], $first['blockers']);
        self::assertSame(ExitPolicy::SUCCESS, $first['exit_code']);
        self::assertNull($first['error']);
        self::assertSame([
            'packages' => 2,
            'ready_packages' => 2,
            'files' => 27,
            'dependencies' => 5,
            'blockers' => 0,
        ], $first['summary']);
        $packages = $first['packages'];
        self::assertIsArray($packages);
        self::assertSame(['User', 'Order'], array_column(array_column($packages, 'module'), 'name'));
        self::assertSame(
            ['acme/user-module', 'acme/order-module'],
            array_column(array_column($packages, 'package'), 'name'),
        );
        self::assertSame(['^1.0', '^1.0'], array_column(array_column($packages, 'package'), 'constraint'));
        $userPackage = $packages[0] ?? null;
        $orderPackage = $packages[1] ?? null;
        self::assertIsArray($userPackage);
        self::assertIsArray($orderPackage);
        self::assertSame(2, $userPackage['schema_version']);
        self::assertSame(2, $orderPackage['schema_version']);
        $dependencies = $orderPackage['dependencies'] ?? null;
        self::assertIsArray($dependencies);
        $orderDependencies = array_values(array_filter(
            $dependencies,
            static fn (mixed $dependency): bool => is_array($dependency)
                && ($dependency['kind'] ?? null) === 'module',
        ));
        self::assertSame([[
            'kind' => 'module',
            'source' => 'User=Workbench\App\Modules\User\UserModule',
            'package' => 'acme/user-module',
            'constraint' => '^1.0',
            'status' => 'resolved',
            'namespace' => 'Acme\UserModule',
        ]], $orderDependencies);
        self::assertDirectoryDoesNotExist(base_path('packages/set-user-module'));
        self::assertDirectoryDoesNotExist(base_path('packages/set-order-module'));
    }

    /** @throws JsonException */
    public function test_missing_selected_dependency_blocks_with_closure_evidence(): void
    {
        [$exitCode, $payload] = $this->jsonOutput([
            '--package' => ['Order=acme/order-module:^1.0=>Acme\OrderModule'],
            '--target' => ['Order=packages/set-order-only'],
            '--format' => 'json',
        ]);

        self::assertSame(ExitPolicy::VIOLATIONS_FOUND, $exitCode);
        self::assertSame('blocked', $payload['status']);
        self::assertSame([], $payload['order']);
        $blockers = $payload['blockers'];
        $packages = $payload['packages'];
        $summary = $payload['summary'];
        self::assertIsArray($blockers);
        self::assertIsArray($packages);
        self::assertIsArray($summary);
        $closure = $blockers[0] ?? null;
        $package = $packages[0] ?? null;
        self::assertIsArray($closure);
        self::assertIsArray($package);
        self::assertSame('MOD-EXPORT-SET-CLOSURE-001', $closure['code']);
        self::assertSame(['Order->User'], $closure['evidence']);
        self::assertSame(2, $summary['blockers']);
        $packageBlockers = $package['blockers'] ?? null;
        self::assertIsArray($packageBlockers);
        $dependencyBlocker = $packageBlockers[0] ?? null;
        self::assertIsArray($dependencyBlocker);
        self::assertSame('MOD-EXPORT-DEPENDENCY-001', $dependencyBlocker['code']);
        self::assertDirectoryDoesNotExist(base_path('packages/set-order-only'));
    }

    /** @throws JsonException */
    public function test_it_materializes_every_package_after_preparing_the_complete_set(): void
    {
        $arguments = [
            '--package' => [
                'Order=acme/order-module:^1.0=>Acme\OrderModule',
                'User=acme/user-module:^1.0=>Acme\UserModule',
            ],
            '--target' => [
                'Order=packages/set-materialized-order',
                'User=packages/set-materialized-user',
            ],
            '--format' => 'json',
        ];
        $filesystem = new Filesystem;
        $userTarget = base_path('packages/set-materialized-user');
        $orderTarget = base_path('packages/set-materialized-order');

        try {
            [, $planned] = $this->jsonOutput($arguments);
            $arguments['--materialize'] = true;
            [$exitCode, $exported] = $this->jsonOutput($arguments);

            self::assertSame(ExitPolicy::SUCCESS, $exitCode);
            self::assertSame('exported', $exported['status']);
            self::assertTrue($exported['complete']);
            self::assertFalse($exported['dry_run']);
            self::assertSame([
                'packages/set-materialized-user',
                'packages/set-materialized-order',
            ], $exported['published_targets']);
            self::assertSame([], $exported['published_before_rollback']);
            self::assertSame([], $exported['remaining_targets']);
            self::assertSame([], $exported['rollback_failures']);
            self::assertSame($planned['packages'], $exported['packages']);
            self::assertDirectoryExists($userTarget);
            self::assertDirectoryExists($orderTarget);
            $orderComposer = json_decode(
                $filesystem->get($orderTarget.'/composer.json'),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
            self::assertIsArray($orderComposer);
            $requirements = $orderComposer['require'] ?? null;
            self::assertIsArray($requirements);
            self::assertSame('^1.0', $requirements['acme/user-module']);
            self::assertStringContainsString(
                'use Acme\UserModule\UserModule;',
                $filesystem->get($orderTarget.'/src/OrderModule.php'),
            );
            self::assertSame([], glob(base_path('packages/.moduark-export-set-*')) ?: []);
        } finally {
            $filesystem->deleteDirectory($userTarget);
            $filesystem->deleteDirectory($orderTarget);
        }
    }

    /** @throws JsonException */
    public function test_second_publish_failure_rolls_back_the_first_target_and_all_staging(): void
    {
        $filesystem = new FailingPackageSetPublishFilesystem(2);
        $this->application()->instance(ModuleExportFilesystem::class, $filesystem);
        $userTarget = base_path('app/moduark-set-rollback-user');
        $orderTarget = base_path('app/moduark-set-rollback-order');

        try {
            [$exitCode, $payload] = $this->jsonOutput([
                '--package' => [
                    'Order=acme/order-module:^1.0=>Acme\OrderModule',
                    'User=acme/user-module:^1.0=>Acme\UserModule',
                ],
                '--target' => [
                    'Order=app/moduark-set-rollback-order',
                    'User=app/moduark-set-rollback-user',
                ],
                '--materialize' => true,
                '--format' => 'json',
            ]);

            self::assertSame(ExitPolicy::TOOL_ERROR, $exitCode);
            self::assertSame('error', $payload['status']);
            self::assertFalse($payload['complete']);
            self::assertFalse($payload['dry_run']);
            self::assertSame([], $payload['published_targets']);
            self::assertSame(['app/moduark-set-rollback-user'], $payload['published_before_rollback']);
            self::assertSame([], $payload['remaining_targets']);
            self::assertSame([], $payload['rollback_failures']);
            self::assertIsString($payload['error']);
            self::assertStringContainsString('Injected package-set publish failure', $payload['error']);
            self::assertDirectoryDoesNotExist($userTarget);
            self::assertDirectoryDoesNotExist($orderTarget);
            self::assertSame([], glob(base_path('app/.moduark-export-set-*')) ?: []);
        } finally {
            $filesystem->native()->delete($userTarget);
            $filesystem->native()->delete($orderTarget);
        }
    }

    /** @throws JsonException */
    public function test_late_collision_is_preserved_and_never_reported_as_published(): void
    {
        $filesystem = new LateCollisionPackageSetFilesystem;
        $this->application()->instance(ModuleExportFilesystem::class, $filesystem);
        $userTarget = base_path('app/moduark-set-late-user');
        $orderTarget = base_path('app/moduark-set-late-order');

        try {
            [$exitCode, $payload] = $this->jsonOutput([
                '--package' => [
                    'User=acme/user-module:^1.0=>Acme\UserModule',
                    'Order=acme/order-module:^1.0=>Acme\OrderModule',
                ],
                '--target' => [
                    'User=app/moduark-set-late-user',
                    'Order=app/moduark-set-late-order',
                ],
                '--materialize' => true,
                '--format' => 'json',
            ]);

            self::assertSame(ExitPolicy::TOOL_ERROR, $exitCode);
            self::assertSame([], $payload['published_before_rollback']);
            self::assertSame([], $payload['remaining_targets']);
            self::assertSame([], $payload['rollback_failures']);
            self::assertFileExists($userTarget.'/external.txt');
            self::assertSame("external collision\n", file_get_contents($userTarget.'/external.txt'));
            self::assertDirectoryDoesNotExist($orderTarget);
            self::assertSame([], glob(base_path('app/.moduark-export-set-*')) ?: []);
        } finally {
            $filesystem->native()->delete($userTarget);
            $filesystem->native()->delete($orderTarget);
        }
    }

    /** @throws JsonException */
    public function test_failed_rollback_reports_the_remaining_published_target(): void
    {
        $filesystem = new FailingPackageSetPublishFilesystem(
            2,
            base_path('app/moduark-set-remaining-user'),
        );
        $this->application()->instance(ModuleExportFilesystem::class, $filesystem);
        $userTarget = base_path('app/moduark-set-remaining-user');
        $orderTarget = base_path('app/moduark-set-remaining-order');

        try {
            [$exitCode, $payload] = $this->jsonOutput([
                '--package' => [
                    'User=acme/user-module:^1.0=>Acme\UserModule',
                    'Order=acme/order-module:^1.0=>Acme\OrderModule',
                ],
                '--target' => [
                    'User=app/moduark-set-remaining-user',
                    'Order=app/moduark-set-remaining-order',
                ],
                '--materialize' => true,
                '--format' => 'json',
            ]);

            self::assertSame(ExitPolicy::TOOL_ERROR, $exitCode);
            self::assertSame(['app/moduark-set-remaining-user'], $payload['published_before_rollback']);
            self::assertSame(['app/moduark-set-remaining-user'], $payload['remaining_targets']);
            self::assertSame([$userTarget], $payload['rollback_failures']);
            self::assertDirectoryExists($userTarget);
            self::assertDirectoryDoesNotExist($orderTarget);
        } finally {
            $filesystem->native()->delete($userTarget);
            $filesystem->native()->delete($orderTarget);
        }
    }

    /** @throws JsonException */
    public function test_overlapping_targets_block_the_complete_set(): void
    {
        [$exitCode, $payload] = $this->jsonOutput([
            '--package' => [
                'User=acme/user-module:^1.0=>Acme\UserModule',
                'Order=acme/order-module:^1.0=>Acme\OrderModule',
            ],
            '--target' => [
                'User=packages/overlap',
                'Order=packages/overlap/order',
            ],
            '--format' => 'json',
        ]);

        self::assertSame(ExitPolicy::VIOLATIONS_FOUND, $exitCode);
        self::assertSame(['User', 'Order'], $payload['order']);
        $blockers = $payload['blockers'];
        self::assertIsArray($blockers);
        $targetBlocker = $blockers[0] ?? null;
        self::assertIsArray($targetBlocker);
        self::assertSame('MOD-EXPORT-SET-TARGET-001', $targetBlocker['code']);
        self::assertSame(['packages/overlap<->packages/overlap/order'], $targetBlocker['evidence']);
        self::assertDirectoryDoesNotExist(base_path('packages/overlap'));
    }

    /** @throws JsonException */
    public function test_materialization_keeps_a_blocked_set_read_only_and_reports_no_publish_evidence(): void
    {
        [$exitCode, $payload] = $this->jsonOutput([
            '--package' => [
                'User=acme/user-module:^1.0=>Acme\UserModule',
                'Order=acme/order-module:^1.0=>Acme\OrderModule',
            ],
            '--target' => [
                'User=packages/set-blocked',
                'Order=packages/set-blocked/order',
            ],
            '--materialize' => true,
            '--format' => 'json',
        ]);

        self::assertSame(ExitPolicy::VIOLATIONS_FOUND, $exitCode);
        self::assertSame('blocked', $payload['status']);
        self::assertTrue($payload['complete']);
        self::assertFalse($payload['dry_run']);
        self::assertSame([], $payload['published_targets']);
        self::assertSame([], $payload['published_before_rollback']);
        self::assertSame([], $payload['remaining_targets']);
        self::assertSame([], $payload['rollback_failures']);
        self::assertDirectoryDoesNotExist(base_path('packages/set-blocked'));
    }

    /** @throws JsonException */
    public function test_invalid_set_identity_is_a_tool_error(): void
    {
        foreach ([
            [[], [], 'at least one --package mapping'],
            [
                ['Missing=acme/missing-module:^1.0=>Acme\MissingModule'],
                ['Missing=packages/missing'],
                'Unknown package-set Module [Missing]',
            ],
            [
                ['User=acme/user-module:^1.0=>Acme\UserModule'],
                [],
                'requires one --target mapping',
            ],
            [
                [
                    'User=acme/user-module:^1.0=>Acme\UserModule',
                    'user=acme/other-user-module:^1.0=>Acme\OtherUserModule',
                ],
                ['User=packages/user'],
                'Duplicate package-set mapping for Module [User]',
            ],
            [
                [
                    'User=acme/shared-module:^1.0=>Acme\UserModule',
                    'Workbench=acme/shared-module:^1.0=>Acme\WorkbenchModule',
                ],
                ['User=packages/user', 'Workbench=packages/workbench'],
                'Duplicate package-set Composer package [acme/shared-module]',
            ],
            [
                [
                    'User=acme/user-module:^1.0=>Acme\SharedModule',
                    'Workbench=acme/workbench-module:^1.0=>acme\sharedmodule',
                ],
                ['User=packages/user', 'Workbench=packages/workbench'],
                'Duplicate package-set namespace [acme\sharedmodule]',
            ],
            [
                ['User=cluion/moduark:^1.3=>Acme\UserModule'],
                ['User=packages/user'],
                'conflicts with a generated runtime requirement',
            ],
            [
                ['User=acme/user-module:^1.0=>Acme\UserModule'],
                ['Workbench=packages/workbench'],
                'target Module [Workbench] has no --package mapping',
            ],
            [
                [
                    'User=acme/user-module:^1.0=>Acme\UserModule',
                    'Workbench=acme/workbench-module:^1.0=>Acme\WorkbenchModule',
                ],
                ['User=packages/shared', 'Workbench=PACKAGES/SHARED'],
                'Duplicate package-set target path [PACKAGES/SHARED]',
            ],
            [
                ['User=acme/user-module:^1.0=>Acme\UserModule'],
                ['User=../outside'],
                'portable application-relative path',
            ],
        ] as [$packages, $targets, $message]) {
            [$exitCode, $payload] = $this->jsonOutput([
                '--package' => $packages,
                '--target' => $targets,
                '--format' => 'json',
            ]);

            self::assertSame(ExitPolicy::TOOL_ERROR, $exitCode);
            self::assertSame('error', $payload['status']);
            self::assertFalse($payload['complete']);
            self::assertIsString($payload['error']);
            self::assertStringContainsString($message, $payload['error']);
        }
    }

    /** @throws JsonException */
    public function test_invalid_materialization_input_reports_empty_publish_evidence(): void
    {
        [$exitCode, $payload] = $this->jsonOutput([
            '--package' => [],
            '--target' => [],
            '--materialize' => true,
            '--format' => 'json',
        ]);

        self::assertSame(ExitPolicy::TOOL_ERROR, $exitCode);
        self::assertSame('error', $payload['status']);
        self::assertFalse($payload['complete']);
        self::assertFalse($payload['dry_run']);
        self::assertSame([], $payload['published_targets']);
        self::assertSame([], $payload['published_before_rollback']);
        self::assertSame([], $payload['remaining_targets']);
        self::assertSame([], $payload['rollback_failures']);
    }

    /**
     * @param array<string, bool|list<string>|string> $arguments
     * @return array{int, array<string, mixed>, string}
     * @throws JsonException
     */
    private function jsonOutput(array $arguments): array
    {
        $output = new BufferedOutput;
        $exitCode = $this->application()->make(Kernel::class)->call(
            'moduark:export-set',
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
}

final class FailingPackageSetPublishFilesystem implements ModuleExportFilesystem
{
    private int $moves = 0;

    private NativeModuleExportFilesystem $native;

    public function __construct(
        private readonly int $failOnMove,
        private readonly ?string $failDeleteTarget = null,
    ) {
        $this->native = new NativeModuleExportFilesystem;
    }

    public function native(): NativeModuleExportFilesystem
    {
        return $this->native;
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
        $this->native->write($path, $contents);
    }

    public function moveDirectory(string $source, string $destination): void
    {
        $this->moves++;

        if ($this->moves === $this->failOnMove) {
            throw new \RuntimeException('Injected package-set publish failure.');
        }

        $this->native->moveDirectory($source, $destination);
    }

    public function delete(string $path): void
    {
        if ($this->failDeleteTarget !== null && $path === $this->failDeleteTarget) {
            throw new \RuntimeException('Injected package-set rollback failure.');
        }

        $this->native->delete($path);
    }

    public function removeEmptyDirectory(string $path): void
    {
        $this->native->removeEmptyDirectory($path);
    }
}

final class LateCollisionPackageSetFilesystem implements ModuleExportFilesystem
{
    private bool $injected = false;

    private NativeModuleExportFilesystem $native;

    public function __construct()
    {
        $this->native = new NativeModuleExportFilesystem;
    }

    public function native(): NativeModuleExportFilesystem
    {
        return $this->native;
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
        $this->native->write($path, $contents);
    }

    public function moveDirectory(string $source, string $destination): void
    {
        if (! $this->injected) {
            $this->injected = true;
            $this->native->ensureDirectory($destination);
            $this->native->write($destination.'/external.txt', "external collision\n");
        }

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

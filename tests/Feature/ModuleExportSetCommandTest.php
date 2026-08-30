<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Architecture\ExitPolicy;
use Illuminate\Contracts\Console\Kernel;
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

    /**
     * @param array<string, list<string>|string> $arguments
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

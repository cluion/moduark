<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Architecture\ExitPolicy;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;
use JsonException;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

final class ModuleOperationsCommandTest extends TestCase
{
    public function test_module_migrate_runs_only_manifest_selected_paths(): void
    {
        try {
            Schema::dropIfExists('moduark_orders');

            $this->command('moduark:migrate Order')
                ->expectsOutputToContain('Module migrations completed successfully.')
                ->assertSuccessful();

            self::assertTrue(Schema::hasTable('moduark_orders'));
        } finally {
            Schema::dropIfExists('moduark_orders');
        }
    }

    public function test_module_seed_runs_manifest_selected_seeders(): void
    {
        config(['moduark.order.seeder_ran' => false]);

        $this->command('moduark:seed Order')
            ->expectsOutputToContain('Module seeders completed successfully.')
            ->assertSuccessful();

        self::assertTrue(config('moduark.order.seeder_ran'));
    }

    public function test_module_test_runs_phpunit_and_preserves_selected_paths(): void
    {
        [$exitCode, $payload] = $this->jsonOutput('moduark:test', [
            'module' => 'Order',
            '--runner' => 'phpunit',
        ]);

        self::assertSame(ExitPolicy::SUCCESS, $exitCode);
        self::assertSame('passed', $payload['status']);
        self::assertSame('phpunit', $payload['runner']);
        self::assertIsArray($payload['paths']);
        self::assertCount(1, $payload['paths']);
        self::assertIsString($payload['output']);
        self::assertStringContainsString('1 test', $payload['output']);
        self::assertNull($payload['error']);
    }

    public function test_module_test_no_tests_and_operation_errors_have_stable_exit_codes(): void
    {
        [$exitCode, $payload] = $this->jsonOutput('moduark:test', ['module' => 'User']);

        self::assertSame(ExitPolicy::VIOLATIONS_FOUND, $exitCode);
        self::assertSame('no_tests', $payload['status']);
        self::assertSame([], $payload['paths']);

        $this->command('moduark:seed Missing')
            ->expectsOutputToContain('Module [Missing] is not active or does not exist.')
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    public function test_operation_json_contracts_are_machine_readable(): void
    {
        config(['moduark.order.seeder_ran' => false]);
        [$seedCode, $seed] = $this->jsonOutput('moduark:seed', ['module' => 'Order']);

        self::assertSame(ExitPolicy::SUCCESS, $seedCode);
        self::assertSame(1, $seed['schema_version']);
        self::assertSame('seed', $seed['operation']);
        self::assertSame('Order', $seed['module']);
        self::assertNotEmpty($seed['classes']);
        self::assertNull($seed['error']);

        [$migrateCode, $migrate] = $this->jsonOutput('moduark:migrate', ['module' => 'User']);

        self::assertSame(ExitPolicy::SUCCESS, $migrateCode);
        self::assertSame('passed', $migrate['status']);
        self::assertSame([], $migrate['paths']);
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array{int, array<mixed>, string}
     * @throws JsonException
     */
    private function jsonOutput(string $command, array $parameters): array
    {
        $output = new BufferedOutput;
        $exitCode = $this->application()->make(Kernel::class)->call(
            $command,
            ['--format' => 'json', ...$parameters],
            $output,
        );
        $json = trim($output->fetch());
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);

        return [$exitCode, $payload, $json];
    }
}

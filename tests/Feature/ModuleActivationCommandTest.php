<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Lifecycle\Activation\ModuleActivationState;
use Illuminate\Contracts\Console\Kernel;
use JsonException;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

final class ModuleActivationCommandTest extends TestCase
{
    public function test_disable_dry_run_is_deterministic_and_does_not_change_state(): void
    {
        $state = $this->application()->make(ModuleActivationState::class);
        $fingerprint = $state->activationSet()->fingerprint();

        [$firstCode, $first, $firstJson] = $this->jsonOutput('moduark:disable', 'Workbench');
        [$secondCode, $second, $secondJson] = $this->jsonOutput('moduark:disable', 'workbench');

        self::assertSame(ExitPolicy::SUCCESS, $firstCode);
        self::assertSame($firstCode, $secondCode);
        self::assertSame($firstJson, $secondJson);
        self::assertSame($first, $second);
        self::assertSame(1, $first['schema_version']);
        self::assertSame('planned', $first['status']);
        self::assertSame('disable', $first['operation']);
        self::assertTrue($first['dry_run']);
        self::assertSame('standalone', $first['driver']);
        self::assertSame(ExitPolicy::SUCCESS, $first['exit_code']);
        self::assertNull($first['error']);
        self::assertIsArray($first['plan']);
        self::assertTrue($first['plan']['executable']);
        self::assertIsArray($first['plan']['after']);
        self::assertNotContains('Workbench', $first['plan']['after']);
        self::assertSame($fingerprint, $state->activationSet()->fingerprint());
        self::assertTrue($state->activationSet()->includes('Workbench'));
    }

    public function test_disable_dry_run_reports_dependency_blockers(): void
    {
        [$exitCode, $payload] = $this->jsonOutput(
            'moduark:disable',
            'User',
            [ExitPolicy::VIOLATIONS_FOUND],
        );

        self::assertSame(ExitPolicy::VIOLATIONS_FOUND, $exitCode);
        self::assertSame('blocked', $payload['status']);
        self::assertSame(ExitPolicy::VIOLATIONS_FOUND, $payload['exit_code']);
        self::assertNull($payload['error']);
        self::assertIsArray($payload['plan']);
        self::assertFalse($payload['plan']['executable']);
        self::assertIsArray($payload['plan']['blockers']);
        self::assertIsArray($payload['plan']['blockers'][0]);
        self::assertSame('missing-dependency', $payload['plan']['blockers'][0]['code']);
    }

    public function test_enable_dry_run_reports_a_validated_no_op(): void
    {
        [$exitCode, $payload] = $this->jsonOutput('moduark:enable', 'Workbench');

        self::assertSame(ExitPolicy::SUCCESS, $exitCode);
        self::assertSame('planned', $payload['status']);
        self::assertSame('enable', $payload['operation']);
        self::assertIsArray($payload['plan']);
        self::assertTrue($payload['plan']['no_op']);
        self::assertTrue($payload['plan']['executable']);
    }

    public function test_commands_refuse_mutation_and_report_input_errors(): void
    {
        $this->command('moduark:disable Workbench')
            ->expectsOutputToContain('pass --dry-run')
            ->assertExitCode(ExitPolicy::TOOL_ERROR);

        [$unknownCode, $unknown] = $this->jsonOutput(
            'moduark:enable',
            'Missing',
            [ExitPolicy::TOOL_ERROR],
        );

        self::assertSame(ExitPolicy::TOOL_ERROR, $unknownCode);
        self::assertSame('error', $unknown['status']);
        self::assertNull($unknown['plan']);
        self::assertSame('Unknown Module [Missing].', $unknown['error']);

        $this->command('moduark:enable Workbench --dry-run --format=xml')
            ->expectsOutputToContain('must be text or json')
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    /**
     * @param list<int> $allowedExitCodes
     * @return array{int, array<mixed>, string}
     * @throws JsonException
     */
    private function jsonOutput(
        string $command,
        string $module,
        array $allowedExitCodes = [ExitPolicy::SUCCESS],
    ): array {
        $output = new BufferedOutput;
        $exitCode = $this->application()->make(Kernel::class)->call(
            $command,
            [
                'module' => $module,
                '--dry-run' => true,
                '--format' => 'json',
            ],
            $output,
        );

        self::assertContains($exitCode, $allowedExitCodes);

        $json = trim($output->fetch());
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);

        return [$exitCode, $payload, $json];
    }
}

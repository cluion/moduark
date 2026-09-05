<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Generation\NativeGeneratorBridgePlanner;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Testing\PendingCommand;
use JsonException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

final class NativeGeneratorBridgeCommandTest extends TestCase
{
    /** @throws JsonException */
    public function test_default_plan_is_disabled_and_does_not_mutate_native_commands(): void
    {
        $before = $this->commandDefinitions();

        [$exitCode, $payload] = $this->jsonPlan();

        self::assertSame(ExitPolicy::SUCCESS, $exitCode);
        self::assertSame(2, $payload['schema_version'] ?? null);
        self::assertSame('disabled', $payload['status'] ?? null);
        self::assertTrue($payload['complete'] ?? false);
        self::assertFalse($payload['opt_in'] ?? true);
        self::assertFalse($payload['mutation'] ?? true);
        self::assertSame('module', $payload['option'] ?? null);
        $summary = $payload['summary'] ?? null;
        self::assertIsArray($summary);
        self::assertSame(31, $summary['candidates'] ?? null);
        self::assertSame(0, $summary['active'] ?? null);
        self::assertSame($before, $this->commandDefinitions());

        foreach ($this->nativeCommands() as $command) {
            self::assertFalse(
                $command->getNativeDefinition()->hasOption('module'),
                "The default configuration injected --module into [{$command->getName()}].",
            );
        }
    }

    /** @throws JsonException */
    public function test_opt_in_blocks_when_testbench_has_replaced_laravel_commands(): void
    {
        $this->enableBridgePlan();

        [$exitCode, $payload] = $this->jsonPlan();

        self::assertSame(ExitPolicy::VIOLATIONS_FOUND, $exitCode);
        self::assertSame('blocked', $payload['status'] ?? null);
        self::assertTrue($payload['opt_in'] ?? false);
        $summary = $payload['summary'] ?? null;
        self::assertIsArray($summary);
        self::assertGreaterThan(0, $summary['blocked'] ?? 0);
        self::assertContains(
            NativeGeneratorBridgePlanner::COMMAND_OWNER_COLLISION,
            $this->diagnosticCodes($payload),
        );
    }

    /** @throws JsonException */
    public function test_existing_module_option_is_a_stable_collision(): void
    {
        $this->enableBridgePlan();
        $trait = $this->nativeCommands()['make:trait'] ?? null;
        self::assertInstanceOf(Command::class, $trait);
        $trait->getNativeDefinition()->addOption(new InputOption('module', null, InputOption::VALUE_REQUIRED));

        [, $payload] = $this->jsonPlan();

        $commands = $payload['commands'] ?? null;
        self::assertIsArray($commands);
        $traitPlan = array_values(array_filter(
            $commands,
            static fn (mixed $command): bool => is_array($command)
                && ($command['command'] ?? null) === 'make:trait',
        ))[0] ?? null;
        self::assertIsArray($traitPlan);
        self::assertSame('blocked', $traitPlan['status'] ?? null);
        $diagnostics = $traitPlan['diagnostics'] ?? null;
        self::assertIsArray($diagnostics);
        self::assertSame(
            [
                NativeGeneratorBridgePlanner::OPTION_COLLISION,
                NativeGeneratorBridgePlanner::REGISTRATION_FAILED,
            ],
            array_column($diagnostics, 'code'),
        );
    }

    /** @throws JsonException */
    public function test_additional_argument_is_a_stable_signature_collision(): void
    {
        $this->enableBridgePlan();
        $trait = $this->nativeCommands()['make:trait'] ?? null;
        self::assertInstanceOf(Command::class, $trait);
        $trait->getNativeDefinition()->addArgument(new InputArgument('context', InputArgument::OPTIONAL));

        [, $payload] = $this->jsonPlan();

        $commands = $payload['commands'] ?? null;
        self::assertIsArray($commands);
        $traitPlan = array_values(array_filter(
            $commands,
            static fn (mixed $command): bool => is_array($command)
                && ($command['command'] ?? null) === 'make:trait',
        ))[0] ?? null;
        self::assertIsArray($traitPlan);
        $diagnostics = $traitPlan['diagnostics'] ?? null;
        self::assertIsArray($diagnostics);
        self::assertSame(
            [
                NativeGeneratorBridgePlanner::SIGNATURE_COLLISION,
                NativeGeneratorBridgePlanner::REGISTRATION_FAILED,
            ],
            array_column($diagnostics, 'code'),
        );
    }

    public function test_invalid_format_fails_without_planning_or_mutation(): void
    {
        $before = $this->commandDefinitions();

        $pending = $this->artisan('moduark:native-bridge', ['--format' => 'xml']);
        self::assertInstanceOf(PendingCommand::class, $pending);
        $pending
            ->expectsOutputToContain('must be text or json')
            ->assertExitCode(ExitPolicy::TOOL_ERROR);

        self::assertSame($before, $this->commandDefinitions());
    }

    private function enableBridgePlan(): void
    {
        $this->application()->instance(ModulesConfig::class, ModulesConfig::from(
            [
                'path' => app_path('Modules'),
                'generation' => ['native_bridge' => false],
                'architecture' => ['level' => 1, 'rules' => []],
            ],
            ['generation' => ['native_bridge' => true]],
        ));
        $this->application()->forgetInstance(NativeGeneratorBridgePlanner::class);
    }

    /**
     * @return array{0: int, 1: array<mixed>}
     * @throws JsonException
     */
    private function jsonPlan(): array
    {
        $output = new BufferedOutput;
        $exitCode = $this->application()->make(Kernel::class)->call(
            'moduark:native-bridge',
            ['--format' => 'json'],
            $output,
        );
        $payload = json_decode($output->fetch(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        return [$exitCode, $payload];
    }

    /** @return array<string, Command> */
    private function nativeCommands(): array
    {
        $commands = [];

        foreach ($this->application()->make(Kernel::class)->all() as $name => $command) {
            if (is_string($name) && $command instanceof Command && str_starts_with($name, 'make:')) {
                $commands[$name] = $command;
            }
        }

        return $commands;
    }

    /** @return array<string, array{class: class-string<Command>, options: list<string>}> */
    private function commandDefinitions(): array
    {
        $definitions = [];

        foreach ($this->nativeCommands() as $name => $command) {
            $options = array_keys($command->getNativeDefinition()->getOptions());
            sort($options, SORT_STRING);
            $definitions[$name] = [
                'class' => $command::class,
                'options' => $options,
            ];
        }

        ksort($definitions, SORT_STRING);

        return $definitions;
    }

    /** @param array<mixed> $payload
     * @return list<string>
     */
    private function diagnosticCodes(array $payload): array
    {
        $codes = [];

        $commands = $payload['commands'] ?? null;

        if (! is_array($commands)) {
            return [];
        }

        foreach ($commands as $command) {
            if (! is_array($command)) {
                continue;
            }

            $diagnostics = $command['diagnostics'] ?? null;

            if (! is_array($diagnostics)) {
                continue;
            }

            foreach ($diagnostics as $diagnostic) {
                if (is_array($diagnostic) && is_string($diagnostic['code'] ?? null)) {
                    $codes[] = $diagnostic['code'];
                }
            }
        }

        return $codes;
    }
}

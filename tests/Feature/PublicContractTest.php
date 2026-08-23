<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Analysis\Baseline\ArchitectureBaseline;
use Cluion\Moduark\Analysis\Suppression\SuppressionManifest;
use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Architecture\Level;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RulePresets;
use Cluion\Moduark\Capability;
use Cluion\Moduark\CapabilityRequirement;
use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Module;
use Cluion\Moduark\ModuarkServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;
use ReflectionNamedType;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

final class PublicContractTest extends TestCase
{
    public function test_documented_php_extension_points_remain_compatible(): void
    {
        $module = new ReflectionClass(Module::class);
        $constructor = $module->getConstructor();

        self::assertTrue($module->isAbstract());
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPublic());
        self::assertTrue($constructor->isFinal());
        self::assertSame(0, $constructor->getNumberOfParameters());

        foreach (['dependencies', 'providers', 'requires', 'provides', 'tables', 'exports'] as $methodName) {
            $method = $module->getMethod($methodName);
            $returnType = $method->getReturnType();

            self::assertTrue($method->isPublic(), "Module::{$methodName}() must remain public.");
            self::assertFalse($method->isFinal(), "Module::{$methodName}() must remain overridable.");
            self::assertSame(0, $method->getNumberOfRequiredParameters());
            self::assertInstanceOf(ReflectionNamedType::class, $returnType);
            self::assertSame('array', $returnType->getName());
        }

        $capability = new ReflectionClass(CandidatePublicCapability::class);
        self::assertTrue($capability->isInterface());
        self::assertTrue($capability->implementsInterface(Capability::class));
        self::assertSame([], (new ReflectionClass(Capability::class))->getMethods());

        $requirement = new CapabilityRequirement(
            CandidatePublicCapability::class,
            CandidatePublicPort::class,
            CandidatePublicAdapter::class,
        );
        $serialized = [
            'capability' => CandidatePublicCapability::class,
            'port' => CandidatePublicPort::class,
            'adapter' => CandidatePublicAdapter::class,
        ];

        self::assertSame(CandidatePublicCapability::class, $requirement->capability());
        self::assertSame(CandidatePublicPort::class, $requirement->port());
        self::assertSame(CandidatePublicAdapter::class, $requirement->adapter());
        self::assertSame($serialized, $requirement->toArray());
        self::assertSame($serialized, CapabilityRequirement::fromArray($serialized)->toArray());
        self::assertTrue(
            (new ReflectionClass(ModuarkServiceProvider::class))->isSubclassOf(ServiceProvider::class),
        );
    }

    public function test_level_rule_and_exit_identities_remain_stable(): void
    {
        self::assertSame([
            'Organization' => 0,
            'Modular' => 1,
            'Decoupled' => 2,
            'Isolated' => 3,
        ], $this->levelValues());
        self::assertSame([
            'valid_module_structure',
            'unique_module_identity',
            'missing_dependencies',
            'undeclared_dependencies',
            'cycles',
            'internal_api_access',
            'capability_contracts',
            'adapter_boundaries',
            'cross_module_model_access',
            'database_ownership',
            'migration_ownership',
            'cross_module_foreign_keys',
            'cross_module_transactions',
            'explicit_public_exports',
        ], array_map(static fn (RuleId $rule): string => $rule->value, RuleId::cases()));
        self::assertSame([
            0 => ['error', 'error', null, null, null, null, null, null, null, null, null, null, null, null],
            1 => ['error', 'error', 'error', 'error', 'error', 'error', null, null, null, null, null, null, null, null],
            2 => ['error', 'error', 'error', 'error', 'error', 'error', 'error', 'error', null, null, null, null, null, null],
            3 => ['error', 'error', 'error', 'error', 'error', 'error', 'error', 'error', 'error', 'error', 'error', 'warning', 'warning', 'error'],
        ], $this->presetMatrix());
        self::assertSame(0, ExitPolicy::SUCCESS);
        self::assertSame(1, ExitPolicy::VIOLATIONS_FOUND);
        self::assertSame(2, ExitPolicy::TOOL_ERROR);
    }

    public function test_configuration_and_versioned_machine_contracts_remain_stable(): void
    {
        $configuration = $this->application()->make(ModulesConfig::class)->all();

        self::assertSame(['path', 'architecture'], array_keys($configuration));
        self::assertIsArray($configuration['architecture']);
        self::assertSame(
            ['level', 'baseline', 'suppressions', 'rules'],
            array_keys($configuration['architecture']),
        );
        self::assertSame(1, ArchitectureBaseline::SCHEMA_VERSION);
        self::assertSame(1, SuppressionManifest::SCHEMA_VERSION);

        $output = new BufferedOutput;
        $exitCode = $this->application()->make(Kernel::class)->call(
            'moduark:check',
            ['--format' => 'json'],
            $output,
        );
        $payload = json_decode(trim($output->fetch()), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(ExitPolicy::SUCCESS, $exitCode);
        self::assertIsArray($payload);
        self::assertSame([
            'schema_version',
            'status',
            'complete',
            'exit_code',
            'architecture',
            'summary',
            'suppressions',
            'baseline',
            'unavailable_rules',
            'results',
            'error',
        ], array_keys($payload));
        self::assertSame(1, $payload['schema_version']);
        self::assertContains($payload['status'], ['passed', 'violations_found', 'incomplete']);
        self::assertTrue($payload['complete']);
        self::assertSame(ExitPolicy::SUCCESS, $payload['exit_code']);
        self::assertNull($payload['error']);
    }

    public function test_documented_command_definitions_remain_stable(): void
    {
        $commands = $this->application()->make(Kernel::class)->all();
        $arguments = [
            'moduark:make-module' => ['name'],
            'moduark:make' => ['module', 'type', 'name'],
            'moduark:list' => [],
            'moduark:inspect' => ['module'],
            'moduark:graph' => ['module'],
            'moduark:check' => [],
            'moduark:baseline' => [],
            'moduark:cache' => [],
            'moduark:clear' => [],
        ];

        foreach ($arguments as $name => $expectedArguments) {
            $command = $this->documentedCommand($commands, $name);

            self::assertSame($expectedArguments, array_keys($command->getDefinition()->getArguments()));
        }

        foreach ([
            'make:module',
            'module:make',
            'module:list',
            'module:inspect',
            'module:graph',
            'module:check',
            'module:baseline',
            'module:cache',
            'module:clear',
        ] as $legacyCommand) {
            self::assertArrayNotHasKey($legacyCommand, $commands);
        }

        foreach (['moduark:make-module' => 'name', 'moduark:inspect' => 'module'] as $command => $argument) {
            self::assertTrue(
                $this->documentedCommand($commands, $command)
                    ->getDefinition()
                    ->getArgument($argument)
                    ->isRequired(),
            );
        }

        foreach (['module', 'type', 'name'] as $argument) {
            self::assertTrue(
                $this->documentedCommand($commands, 'moduark:make')
                    ->getDefinition()
                    ->getArgument($argument)
                    ->isRequired(),
            );
        }

        self::assertFalse(
            $this->documentedCommand($commands, 'moduark:graph')
                ->getDefinition()
                ->getArgument('module')
                ->isRequired(),
        );

        $this->assertOptionDefaults($this->documentedCommand($commands, 'moduark:make'), [
            'dry-run' => false,
            'force' => false,
            'factory' => false,
            'migration' => false,
            'int' => false,
            'string' => false,
            'inbound' => false,
            'render' => false,
            'report' => false,
            'collection' => false,
            'json-api' => false,
            'model' => null,
            'guard' => null,
            'implicit' => false,
            'event' => null,
            'queued' => false,
            'sync' => false,
            'batched' => false,
            'markdown' => null,
            'view' => null,
            'invokable' => false,
            'resource' => false,
            'api' => false,
        ]);
        $this->assertOptionDefaults($this->documentedCommand($commands, 'moduark:make-module'), [
            'preset' => null,
            'dry-run' => false,
        ]);
        $this->assertOptionDefaults($this->documentedCommand($commands, 'moduark:graph'), [
            'view' => 'module',
            'format' => 'text',
        ]);
        $this->assertOptionDefaults($this->documentedCommand($commands, 'moduark:check'), [
            'level' => null,
            'format' => 'text',
            'show-suppressions' => false,
        ]);
        $this->assertOptionDefaults($this->documentedCommand($commands, 'moduark:baseline'), [
            'level' => null,
            'force' => false,
            'prune' => false,
        ]);
    }

    /** @return array<string, int> */
    private function levelValues(): array
    {
        $values = [];

        foreach (Level::cases() as $level) {
            $values[$level->name] = $level->value;
        }

        return $values;
    }

    /** @return array<int, list<?string>> */
    private function presetMatrix(): array
    {
        $presets = new RulePresets;
        $matrix = [];

        foreach (Level::cases() as $level) {
            $matrix[$level->value] = array_map(
                static fn (RuleId $rule): ?string => $presets->severity($level, $rule)?->value,
                RuleId::cases(),
            );
        }

        return $matrix;
    }

    /**
     * @param array<mixed> $commands
     */
    private function documentedCommand(array $commands, string $name): Command
    {
        self::assertArrayHasKey($name, $commands);
        self::assertInstanceOf(Command::class, $commands[$name]);

        return $commands[$name];
    }

    /**
     * @param array<string, mixed> $expected
     */
    private function assertOptionDefaults(Command $command, array $expected): void
    {
        foreach ($expected as $name => $default) {
            $option = $command->getDefinition()->getOption($name);

            self::assertSame($default, $option->getDefault(), "Unexpected --{$name} default.");
        }
    }
}

interface CandidatePublicCapability extends Capability
{
}

interface CandidatePublicPort
{
}

final class CandidatePublicAdapter implements CandidatePublicPort
{
}

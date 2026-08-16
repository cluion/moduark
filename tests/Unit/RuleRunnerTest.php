<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\ArchitectureRule;
use Cluion\Moduark\Analysis\Boundary\ConventionPublicApi;
use Cluion\Moduark\Analysis\CheckReport;
use Cluion\Moduark\Analysis\RuleRunner;
use Cluion\Moduark\Analysis\Rules\AdapterBoundariesRule;
use Cluion\Moduark\Analysis\Rules\CapabilityContractsRule;
use Cluion\Moduark\Analysis\Rules\CrossModuleForeignKeysRule;
use Cluion\Moduark\Analysis\Rules\CrossModuleModelAccessRule;
use Cluion\Moduark\Analysis\Rules\CrossModuleTransactionsRule;
use Cluion\Moduark\Analysis\Rules\DatabaseOwnershipRule;
use Cluion\Moduark\Analysis\Rules\CyclesRule;
use Cluion\Moduark\Analysis\Rules\ExplicitPublicExportsRule;
use Cluion\Moduark\Analysis\Rules\InternalApiAccessRule;
use Cluion\Moduark\Analysis\Rules\MigrationOwnershipRule;
use Cluion\Moduark\Analysis\Rules\MissingDependenciesRule;
use Cluion\Moduark\Analysis\Rules\UndeclaredDependenciesRule;
use Cluion\Moduark\Analysis\Rules\UniqueModuleIdentityRule;
use Cluion\Moduark\Analysis\Rules\ValidModuleStructureRule;
use Cluion\Moduark\Analysis\Source\SourceIndex;
use Cluion\Moduark\Architecture\EffectiveArchitecture;
use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RulePresets;
use Cluion\Moduark\Architecture\RuleResolver;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RuleRunnerTest extends TestCase
{
    public function test_metadata_rules_report_missing_dependencies_and_cyclic_components(): void
    {
        $report = $this->runner()->run(
            $this->invalidGraph(),
            $this->architecture(1, [
                'undeclared_dependencies' => false,
                'internal_api_access' => false,
            ]),
        );

        self::assertTrue($report->complete());
        self::assertCount(4, $report->results());
        self::assertCount(2, $report->errors());
        self::assertSame(ExitPolicy::VIOLATIONS_FOUND, $report->exitCode(new ExitPolicy));

        $missing = $this->findRuleResult($report, RuleId::MissingDependencies)->violations()[0];

        self::assertSame([
            'rule' => 'missing_dependencies',
            'code' => 'MOD-DEPENDENCY-001',
            'severity' => 'error',
            'message' => 'Module [Order] depends on missing Module [Missing].',
            'file' => '/modules/Order/OrderModule.php',
            'line' => null,
            'consumer' => 'Order',
            'target' => 'Missing',
            'symbol' => MissingModule::class,
            'suggestion' => 'Discover the dependency Module or remove its declaration.',
        ], $missing->toArray());

        $cycle = $this->findRuleResult($report, RuleId::Cycles)->violations()[0];

        self::assertSame('MOD-CYCLE-001', $cycle->code());
        self::assertSame('Alpha', $cycle->consumer());
        self::assertSame('Alpha, Beta', $cycle->target());
        self::assertSame(
            'Circular Module dependency detected among [Alpha, Beta].',
            $cycle->message(),
        );
    }

    public function test_disabled_rule_is_not_executed(): void
    {
        $report = $this->runner()->run(
            $this->invalidGraph(),
            $this->architecture(1, [
                'missing_dependencies' => false,
                'cycles' => false,
                'undeclared_dependencies' => false,
                'internal_api_access' => false,
            ]),
        );

        self::assertTrue($report->complete());
        self::assertSame([
            RuleId::ValidModuleStructure,
            RuleId::UniqueModuleIdentity,
        ], array_map(
            static fn (RuleResult $result): RuleId => $result->rule(),
            $report->results(),
        ));
        self::assertSame([], $report->violations());
        self::assertSame(ExitPolicy::SUCCESS, $report->exitCode(new ExitPolicy));
    }

    public function test_all_level_one_rules_have_implementations(): void
    {
        $report = $this->runner()->run($this->validGraph(), $this->architecture(1));

        self::assertTrue($report->complete());
        self::assertSame([], $report->unavailableRules());
        self::assertCount(6, $report->results());
        self::assertSame(ExitPolicy::SUCCESS, $report->exitCode(new ExitPolicy));
    }

    public function test_all_level_two_rules_have_implementations(): void
    {
        $report = $this->runner()->run($this->validGraph(), $this->architecture(2));

        self::assertTrue($report->complete());
        self::assertCount(8, $report->results());
        self::assertSame([], $report->unavailableRules());
        self::assertSame(ExitPolicy::SUCCESS, $report->exitCode(new ExitPolicy));
    }

    public function test_all_level_three_rules_have_implementations(): void
    {
        $report = $this->runner()->run($this->validGraph(), $this->architecture(3));

        self::assertTrue($report->complete());
        self::assertCount(14, $report->results());
        self::assertSame([], $report->unavailableRules());
        self::assertSame(ExitPolicy::SUCCESS, $report->exitCode(new ExitPolicy));
    }

    public function test_level_zero_is_complete_with_discovery_validation_rules(): void
    {
        $report = $this->runner()->run($this->validGraph(), $this->architecture(0));

        self::assertTrue($report->complete());
        self::assertSame([
            RuleId::ValidModuleStructure,
            RuleId::UniqueModuleIdentity,
        ], array_map(
            static fn (RuleResult $result): RuleId => $result->rule(),
            $report->results(),
        ));
        self::assertSame(ExitPolicy::SUCCESS, $report->exitCode(new ExitPolicy));
    }

    public function test_multiple_cyclic_components_are_reported_deterministically(): void
    {
        $registry = new ModuleRegistry([
            $this->module('Self', CheckSelfCycleModule::class),
            $this->module('Beta', CheckCycleBetaModule::class),
            $this->module('Alpha', CheckCycleAlphaModule::class),
        ]);
        $context = new AnalysisContext($registry, [
            new ModuleDescriptor(CheckCycleAlphaModule::class, [CheckCycleBetaModule::class], []),
            new ModuleDescriptor(CheckCycleBetaModule::class, [CheckCycleAlphaModule::class], []),
            new ModuleDescriptor(CheckSelfCycleModule::class, [CheckSelfCycleModule::class], []),
        ], new SourceIndex([], []));
        $result = (new CyclesRule)->inspect(
            $context,
            RuleId::Cycles->defaultSeverity(),
        );

        self::assertSame([
            'Alpha, Beta',
            'Self',
        ], array_map(
            static fn ($violation): ?string => $violation->target(),
            $result->violations(),
        ));
    }

    public function test_runner_rejects_duplicate_rule_ids(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Architecture rule [missing_dependencies] was registered more than once.');

        new RuleRunner([
            new MissingDependenciesRule,
            new MissingDependenciesRule,
        ]);
    }

    public function test_runner_rejects_a_result_for_another_rule(): void
    {
        $rule = new class implements ArchitectureRule
        {
            public function id(): RuleId
            {
                return RuleId::MissingDependencies;
            }

            public function inspect(AnalysisContext $context, Severity $severity): RuleResult
            {
                return new RuleResult(RuleId::Cycles);
            }
        };
        $runner = new RuleRunner([$rule]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Architecture rule [missing_dependencies] returned a result for rule [cycles].',
        );

        $runner->run($this->validGraph(), $this->architecture(0, [
            'valid_module_structure' => false,
            'unique_module_identity' => false,
            'missing_dependencies' => true,
        ]));
    }

    private function runner(): RuleRunner
    {
        return new RuleRunner([
            new ValidModuleStructureRule,
            new UniqueModuleIdentityRule,
            new MissingDependenciesRule,
            new UndeclaredDependenciesRule,
            new CyclesRule,
            new InternalApiAccessRule(new ConventionPublicApi),
            new CapabilityContractsRule,
            new AdapterBoundariesRule,
            new CrossModuleModelAccessRule,
            new DatabaseOwnershipRule,
            new MigrationOwnershipRule,
            new CrossModuleForeignKeysRule,
            new CrossModuleTransactionsRule,
            new ExplicitPublicExportsRule,
        ]);
    }

    private function findRuleResult(CheckReport $report, RuleId $rule): RuleResult
    {
        foreach ($report->results() as $result) {
            if ($result->rule() === $rule) {
                return $result;
            }
        }

        self::fail("Rule result [{$rule->value}] was not produced.");
    }

    private function validGraph(): AnalysisContext
    {
        $registry = new ModuleRegistry([
            $this->module('User', CheckUserModule::class),
            $this->module('Order', CheckOrderModule::class),
        ]);

        return new AnalysisContext($registry, [
            new ModuleDescriptor(CheckOrderModule::class, [CheckUserModule::class], []),
            new ModuleDescriptor(CheckUserModule::class, [], []),
        ], new SourceIndex([], []));
    }

    private function invalidGraph(): AnalysisContext
    {
        $registry = new ModuleRegistry([
            $this->module('Order', CheckOrderModule::class),
            $this->module('Alpha', CheckCycleAlphaModule::class),
            $this->module('Beta', CheckCycleBetaModule::class),
        ]);

        return new AnalysisContext($registry, [
            new ModuleDescriptor(CheckOrderModule::class, [MissingModule::class], []),
            new ModuleDescriptor(CheckCycleAlphaModule::class, [CheckCycleBetaModule::class], []),
            new ModuleDescriptor(CheckCycleBetaModule::class, [CheckCycleAlphaModule::class], []),
        ], new SourceIndex([], []));
    }

    /**
     * @param class-string<Module> $moduleClass
     */
    private function module(string $name, string $moduleClass): DiscoveredModule
    {
        return new DiscoveredModule(
            $name,
            $moduleClass,
            "/modules/{$name}/{$name}Module.php",
            __NAMESPACE__,
        );
    }

    /**
     * @param array<string, bool> $rules
     */
    private function architecture(int $level, array $rules = []): EffectiveArchitecture
    {
        $configuration = ModulesConfig::from(
            [
                'path' => '/modules',
                'architecture' => [
                    'level' => 1,
                    'rules' => [],
                ],
            ],
            [
                'architecture' => [
                    'level' => $level,
                    'rules' => $rules,
                ],
            ],
        );

        return (new RuleResolver(new RulePresets))->resolve($configuration);
    }
}

final class CheckUserModule extends Module
{
}

final class CheckOrderModule extends Module
{
}

final class MissingModule extends Module
{
}

final class CheckCycleAlphaModule extends Module
{
}

final class CheckCycleBetaModule extends Module
{
}

final class CheckSelfCycleModule extends Module
{
}

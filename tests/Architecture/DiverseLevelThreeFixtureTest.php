<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Cluion\Moduark\Analysis\ArchitectureChecker;
use Cluion\Moduark\Analysis\Boundary\ConventionPublicApi;
use Cluion\Moduark\Analysis\RuleRunner;
use Cluion\Moduark\Analysis\Rules\AdapterBoundariesRule;
use Cluion\Moduark\Analysis\Rules\CapabilityContractsRule;
use Cluion\Moduark\Analysis\Rules\CrossModuleForeignKeysRule;
use Cluion\Moduark\Analysis\Rules\CrossModuleModelAccessRule;
use Cluion\Moduark\Analysis\Rules\CrossModuleTransactionsRule;
use Cluion\Moduark\Analysis\Rules\CyclesRule;
use Cluion\Moduark\Analysis\Rules\DatabaseOwnershipRule;
use Cluion\Moduark\Analysis\Rules\ExplicitPublicExportsRule;
use Cluion\Moduark\Analysis\Rules\InternalApiAccessRule;
use Cluion\Moduark\Analysis\Rules\MigrationOwnershipRule;
use Cluion\Moduark\Analysis\Rules\MissingDependenciesRule;
use Cluion\Moduark\Analysis\Rules\UndeclaredDependenciesRule;
use Cluion\Moduark\Analysis\Rules\UniqueModuleIdentityRule;
use Cluion\Moduark\Analysis\Rules\ValidModuleStructureRule;
use Cluion\Moduark\Analysis\Source\SourceIndexBuilder;
use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Architecture\RulePresets;
use Cluion\Moduark\Architecture\RuleResolver;
use Cluion\Moduark\Capabilities\CapabilityResolver;
use Cluion\Moduark\Lifecycle\ModuleLifecycleRegistrar;
use Cluion\Moduark\Lifecycle\ModuleOrderer;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\DiverseLevelThree\DiverseLevelThreeFixture;
use Tests\Fixtures\DiverseLevelThree\Modules\Order\Actions\StartOrder;

final class DiverseLevelThreeFixtureTest extends TestCase
{
    public function test_full_level_three_preset_is_complete_without_blocking_false_positives(): void
    {
        $report = $this->checker()->check();

        self::assertTrue($report->complete());
        self::assertCount(14, $report->results());
        self::assertSame([], $report->errors());
        self::assertSame(ExitPolicy::SUCCESS, $report->exitCode(new ExitPolicy));
        self::assertSame([
            'MOD-TABLE-003',
            'MOD-FK-001',
            'MOD-FK-002',
            'MOD-TRANSACTION-002',
        ], array_map(
            static fn ($warning): string => $warning->code(),
            $report->warnings(),
        ));
    }

    public function test_source_evidence_covers_owned_migrations_foreign_keys_and_transactions(): void
    {
        $index = (new SourceIndexBuilder(DiverseLevelThreeFixture::registry()))->build();

        self::assertCount(3, $index->tableAccesses());
        self::assertCount(5, $index->schemaMutations());
        self::assertCount(4, $index->foreignKeyReferences());
        self::assertCount(2, $index->transactionScopes());
        self::assertSame(
            ['orders', 'order_items', null],
            array_map(static fn ($access): ?string => $access->table(), $index->tableAccesses()),
        );
        self::assertSame(
            [2, 1],
            array_map(static fn ($scope): int => count($scope->writes()), $index->transactionScopes()),
        );
    }

    public function test_two_consumer_owned_ports_are_wired_and_executable(): void
    {
        $application = new Application;
        $compiler = new ModuleMetadataCompiler;
        $plan = (new CapabilityResolver)->resolve(
            $compiler->compileAll(DiverseLevelThreeFixture::moduleClasses()),
        );
        $registrar = new ModuleLifecycleRegistrar(
            $application,
            $compiler,
            new ModuleOrderer,
            new CapabilityResolver,
        );

        self::assertCount(2, $plan->bindings());
        $registrar->registerProviders(DiverseLevelThreeFixture::moduleClasses());

        foreach (DiverseLevelThreeFixture::ports() as $port) {
            self::assertTrue($application->bound($port), $port);
        }

        self::assertSame(
            'Customer 42 | authorized',
            $application->make(StartOrder::class)->for(42, 1250),
        );
    }

    public function test_level_three_promotion_remains_blocked_on_independent_adoption(): void
    {
        $policy = $this->contents('docs/stability.md');
        $decision = $this->contents('docs/adr/0046-level-three-preview-go-no-go.md');

        self::assertStringContainsString(
            'go/no-go review keeps Level 3 in Preview',
            $policy,
        );
        self::assertStringContainsString(
            'Level 3 remains **Preview** in `1.0.0`',
            $decision,
        );
        self::assertStringContainsString(
            'No analyzer implementation or diagnostic semantics are changed',
            $decision,
        );
        self::assertMatchesRegularExpression(
            '/at least two\s+independently\s+maintained Laravel applications/',
            $decision,
        );
    }

    private function checker(): ArchitectureChecker
    {
        $registry = DiverseLevelThreeFixture::registry();
        $publicApi = new ConventionPublicApi;

        return new ArchitectureChecker(
            $registry,
            new ModuleMetadataCompiler,
            new SourceIndexBuilder($registry),
            DiverseLevelThreeFixture::configuration(),
            new RuleResolver(new RulePresets),
            new RuleRunner([
                new ValidModuleStructureRule,
                new UniqueModuleIdentityRule,
                new MissingDependenciesRule,
                new UndeclaredDependenciesRule,
                new CyclesRule,
                new InternalApiAccessRule($publicApi),
                new CapabilityContractsRule,
                new AdapterBoundariesRule,
                new CrossModuleModelAccessRule,
                new DatabaseOwnershipRule,
                new MigrationOwnershipRule,
                new CrossModuleForeignKeysRule,
                new CrossModuleTransactionsRule,
                new ExplicitPublicExportsRule,
            ]),
        );
    }

    private function contents(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/'.$path);

        self::assertNotFalse($contents, "Unable to read [{$path}].");

        return $contents;
    }
}

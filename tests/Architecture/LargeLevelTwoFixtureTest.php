<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Cluion\Moduark\Analysis\ArchitectureChecker;
use Cluion\Moduark\Analysis\Boundary\ConventionPublicApi;
use Cluion\Moduark\Analysis\RuleRunner;
use Cluion\Moduark\Analysis\Rules\AdapterBoundariesRule;
use Cluion\Moduark\Analysis\Rules\CapabilityContractsRule;
use Cluion\Moduark\Analysis\Rules\CyclesRule;
use Cluion\Moduark\Analysis\Rules\InternalApiAccessRule;
use Cluion\Moduark\Analysis\Rules\MissingDependenciesRule;
use Cluion\Moduark\Analysis\Rules\UndeclaredDependenciesRule;
use Cluion\Moduark\Analysis\Rules\UniqueModuleIdentityRule;
use Cluion\Moduark\Analysis\Rules\ValidModuleStructureRule;
use Cluion\Moduark\Analysis\Source\SourceIndexBuilder;
use Cluion\Moduark\Architecture\RulePresets;
use Cluion\Moduark\Architecture\RuleResolver;
use Cluion\Moduark\Capabilities\CapabilityResolver;
use Cluion\Moduark\Graph\CapabilityGraphBuilder;
use Cluion\Moduark\Graph\CombinedGraphBuilder;
use Cluion\Moduark\Graph\Export\TextCombinedGraphExporter;
use Cluion\Moduark\Graph\ModuleGraphBuilder;
use Cluion\Moduark\Inspection\ModuleInspectionBuilder;
use Cluion\Moduark\Lifecycle\ModuleLifecycleRegistrar;
use Cluion\Moduark\Lifecycle\ModuleOrderer;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\LargeLevelTwo\Capabilities\PaymentAuthorization;
use Tests\Fixtures\LargeLevelTwo\LargeLevelTwoFixture;
use Tests\Fixtures\LargeLevelTwo\Modules\Checkout\Actions\StartCheckout;
use Tests\Fixtures\LargeLevelTwo\Modules\Fulfillment\Actions\PlanFulfillment;
use Tests\Fixtures\LargeLevelTwo\Modules\Returns\Actions\ApproveReturn;
use Tests\Fixtures\LargeLevelTwo\Modules\Returns\ReturnsModule;

final class LargeLevelTwoFixtureTest extends TestCase
{
    public function test_all_eight_level_two_rules_pass_for_the_large_fixture(): void
    {
        $report = $this->checker()->check();

        self::assertTrue($report->complete());
        self::assertCount(8, $report->results());
        self::assertSame([], $report->violations());

        foreach ($report->results() as $result) {
            self::assertTrue($result->passed(), $result->rule()->value);
        }
    }

    public function test_twelve_consumer_owned_ports_are_wired_at_runtime(): void
    {
        $compiler = new ModuleMetadataCompiler;
        $modules = LargeLevelTwoFixture::moduleClasses();
        $plan = (new CapabilityResolver)->resolve($compiler->compileAll($modules));
        $application = new Application;
        $registrar = new ModuleLifecycleRegistrar(
            $application,
            $compiler,
            new ModuleOrderer,
            new CapabilityResolver,
        );

        self::assertCount(12, $plan->bindings());

        $registrar->registerProviders($modules);

        foreach (LargeLevelTwoFixture::ports() as $port) {
            self::assertTrue($application->bound($port), $port);
        }

        self::assertSame(
            'Customer 42 | Product SKU-1 | in stock | authorized',
            $application->make(StartCheckout::class)->for(42, 'SKU-1'),
        );
        self::assertSame(
            'Customer 42 | in stock | fulfillment queued',
            $application->make(PlanFulfillment::class)->for(42, 'SKU-1'),
        );
        self::assertSame(
            'Customer 42 | Product SKU-1 | in stock | authorized | return approved',
            $application->make(ApproveReturn::class)->for(42, 'SKU-1'),
        );
    }

    public function test_graph_and_inspection_preserve_the_complete_fixture(): void
    {
        $registry = LargeLevelTwoFixture::registry();
        $compiler = new ModuleMetadataCompiler;
        $combinedBuilder = new CombinedGraphBuilder(
            new ModuleGraphBuilder($registry, $compiler),
            new CapabilityGraphBuilder($registry, $compiler, new CapabilityResolver),
        );
        $combined = $combinedBuilder->build();

        self::assertCount(8, $combined->moduleGraph()->discoveredNodes());
        self::assertCount(12, $combined->moduleGraph()->edges());
        self::assertCount(5, $combined->capabilityGraph()->capabilities());
        self::assertCount(17, $combined->capabilityGraph()->edges());

        $text = (new TextCombinedGraphExporter)->export($combined);
        self::assertStringContainsString('Checkout -[depends]-> Customer', $text);
        self::assertStringContainsString('Customer -[provides]-> CustomerLookup', $text);
        self::assertStringContainsString('Returns -[requires]-> PaymentAuthorization', $text);

        $inspection = (new ModuleInspectionBuilder(
            $registry,
            $compiler,
            $combinedBuilder,
            new SourceIndexBuilder($registry),
            new ConventionPublicApi,
            (new RuleResolver(new RulePresets))->resolve(LargeLevelTwoFixture::configuration()),
        ))->build('returns');

        self::assertSame(ReturnsModule::class, $inspection->module()->moduleClass());
        self::assertCount(5, $inspection->dependencies());
        self::assertCount(5, $inspection->descriptor()->requires());
        self::assertSame(
            'Payment',
            $inspection->capabilityProvider(PaymentAuthorization::class)->name(),
        );
        self::assertSame(
            [ReturnsModule::class],
            array_map(static fn ($symbol): string => $symbol->name(), $inspection->publicApi()),
        );
    }

    private function checker(): ArchitectureChecker
    {
        $registry = LargeLevelTwoFixture::registry();
        $publicApi = new ConventionPublicApi;

        return new ArchitectureChecker(
            $registry,
            new ModuleMetadataCompiler,
            new SourceIndexBuilder($registry),
            LargeLevelTwoFixture::configuration(),
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
            ]),
        );
    }
}

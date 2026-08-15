<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Capabilities\CapabilityResolver;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Exceptions\CombinedGraphFailed;
use Cluion\Moduark\Graph\CapabilityGraph;
use Cluion\Moduark\Graph\CapabilityGraphBuilder;
use Cluion\Moduark\Graph\CombinedGraph;
use Cluion\Moduark\Graph\CombinedGraphBuilder;
use Cluion\Moduark\Graph\Export\MermaidCombinedGraphExporter;
use Cluion\Moduark\Graph\Export\TextCombinedGraphExporter;
use Cluion\Moduark\Graph\ModuleGraph;
use Cluion\Moduark\Graph\ModuleGraphBuilder;
use Cluion\Moduark\Graph\ModuleGraphNode;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\LevelTwo\Modules\Checkout\CheckoutModule;
use Tests\Fixtures\LevelTwo\Modules\Order\OrderModule;
use Tests\Fixtures\LevelTwo\Modules\User\UserModule;

final class CombinedGraphExporterTest extends TestCase
{
    public function test_it_exports_a_deterministic_text_view_with_all_edge_kinds(): void
    {
        $first = (new TextCombinedGraphExporter)->export($this->graph([
            $this->module('Order', OrderModule::class),
            $this->module('Inventory', CombinedExporterInventoryModule::class),
            $this->module('User', UserModule::class),
            $this->module('Checkout', CheckoutModule::class),
        ]));
        $second = (new TextCombinedGraphExporter)->export($this->graph([
            $this->module('Checkout', CheckoutModule::class),
            $this->module('User', UserModule::class),
            $this->module('Inventory', CombinedExporterInventoryModule::class),
            $this->module('Order', OrderModule::class),
        ]));

        $expected = <<<'TEXT'
Checkout -[depends]-> User
Checkout -[requires]-> UserLookup
Inventory -> —
Order -[depends]-> User
Order -[requires]-> UserLookup
User -[provides]-> UserLookup
TEXT;

        self::assertSame($expected, $first);
        self::assertSame($expected, $second);
    }

    public function test_it_exports_a_deterministic_mermaid_view(): void
    {
        $graph = $this->levelTwoGraph();

        self::assertSame(<<<'MERMAID'
flowchart LR
    M0["Checkout"]
    M1["Inventory"]
    M2["Order"]
    M3["User"]
    C0(["UserLookup"])
    M0 -->|"depends"| M3
    M2 -->|"depends"| M3
    M3 -->|"provides"| C0
    M0 -->|"requires"| C0
    M2 -->|"requires"| C0
MERMAID, (new MermaidCombinedGraphExporter)->export($graph));
    }

    public function test_it_preserves_missing_direct_dependency_targets(): void
    {
        $graph = $this->graph([
            $this->module('MissingConsumer', CombinedMissingConsumerModule::class),
        ]);

        self::assertSame(
            'MissingConsumer -[depends]-> CombinedMissing [missing]',
            (new TextCombinedGraphExporter)->export($graph),
        );
        self::assertStringContainsString(
            'M0["CombinedMissing (missing)"]:::missing',
            (new MermaidCombinedGraphExporter)->export($graph),
        );
    }

    public function test_module_neighborhood_unions_direct_and_capability_relationships(): void
    {
        $graph = $this->levelTwoGraph()->neighborhood('order');

        self::assertSame(<<<'TEXT'
Checkout -[depends]-> User
Checkout -[requires]-> UserLookup
Order -[depends]-> User
Order -[requires]-> UserLookup
User -[provides]-> UserLookup
TEXT, (new TextCombinedGraphExporter)->export($graph));
    }

    public function test_isolated_module_neighborhood_remains_visible(): void
    {
        $graph = $this->levelTwoGraph()->neighborhood('inventory');

        self::assertSame(
            'Inventory -> —',
            (new TextCombinedGraphExporter)->export($graph),
        );
    }

    public function test_unknown_module_neighborhood_is_rejected(): void
    {
        $this->expectException(CombinedGraphFailed::class);
        $this->expectExceptionMessage(
            'Module [Unknown] was not found in the combined graph.',
        );

        $this->levelTwoGraph()->neighborhood('Unknown');
    }

    public function test_it_rejects_module_sets_that_differ_between_views(): void
    {
        $module = new ModuleGraphNode(
            'Contract',
            CombinedContractModule::class,
            '/modules/Contract/ContractModule.php',
            true,
        );

        $this->expectException(CombinedGraphFailed::class);
        $this->expectExceptionMessage(
            'Combined graph Module ['.CombinedContractModule::class
            .'] is missing from the Capability graph.',
        );

        new CombinedGraph(
            new ModuleGraph([$module], []),
            new CapabilityGraph([], [], []),
        );
    }

    private function levelTwoGraph(): CombinedGraph
    {
        return $this->graph([
            $this->module('Order', OrderModule::class),
            $this->module('Inventory', CombinedExporterInventoryModule::class),
            $this->module('User', UserModule::class),
            $this->module('Checkout', CheckoutModule::class),
        ]);
    }

    /**
     * @param list<DiscoveredModule> $modules
     */
    private function graph(array $modules): CombinedGraph
    {
        $registry = new ModuleRegistry($modules);

        return (new CombinedGraphBuilder(
            new ModuleGraphBuilder($registry, new ModuleMetadataCompiler),
            new CapabilityGraphBuilder(
                $registry,
                new ModuleMetadataCompiler,
                new CapabilityResolver,
            ),
        ))->build();
    }

    /** @param class-string<Module> $moduleClass */
    private function module(string $name, string $moduleClass): DiscoveredModule
    {
        return new DiscoveredModule(
            $name,
            $moduleClass,
            "/modules/{$name}/{$name}Module.php",
            __NAMESPACE__,
        );
    }
}

final class CombinedExporterInventoryModule extends Module
{
}

final class CombinedMissingModule extends Module
{
}

final class CombinedMissingConsumerModule extends Module
{
    /** @return list<class-string<Module>> */
    public function dependencies(): array
    {
        return [CombinedMissingModule::class];
    }
}

final class CombinedContractModule extends Module
{
}

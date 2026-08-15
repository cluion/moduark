<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Exceptions\ModuleGraphFailed;
use Cluion\Moduark\Graph\Export\MermaidModuleGraphExporter;
use Cluion\Moduark\Graph\Export\TextModuleGraphExporter;
use Cluion\Moduark\Graph\ModuleGraphBuilder;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use PHPUnit\Framework\TestCase;

final class ModuleGraphTest extends TestCase
{
    public function test_it_builds_and_exports_a_deterministic_graph_with_isolated_modules(): void
    {
        $graph = $this->builder([
            $this->module('Payment', GraphPaymentModule::class),
            $this->module('Inventory', GraphInventoryModule::class),
            $this->module('User', GraphUserModule::class),
            $this->module('Order', GraphOrderModule::class),
        ])->build();

        self::assertSame(
            "Inventory -> —\nOrder -> User\nPayment -> Order\nUser -> —",
            (new TextModuleGraphExporter)->export($graph),
        );
        self::assertSame(<<<'MERMAID'
flowchart LR
    M0["Inventory"]
    M1["Order"]
    M2["Payment"]
    M3["User"]
    M1 --> M3
    M2 --> M1
MERMAID, (new MermaidModuleGraphExporter)->export($graph));
        self::assertSame(
            GraphOrderModule::class.'::dependencies()',
            $graph->edges()[0]->evidence(),
        );
    }

    public function test_it_preserves_and_marks_missing_dependency_targets(): void
    {
        $graph = $this->builder([
            $this->module('Order', GraphMissingConsumerModule::class),
        ])->build();

        self::assertSame(
            'Order -> GraphMissing [missing]',
            (new TextModuleGraphExporter)->export($graph),
        );
        self::assertSame(<<<'MERMAID'
flowchart LR
    M0["GraphMissing (missing)"]:::missing
    M1["Order"]
    M1 --> M0
    classDef missing fill:#fff3cd,stroke:#d39e00,stroke-dasharray: 5 5
MERMAID, (new MermaidModuleGraphExporter)->export($graph));
        self::assertFalse($graph->node(GraphMissingModule::class)->discovered());
        self::assertNull($graph->node(GraphMissingModule::class)->path());
    }

    public function test_it_exports_cycles_without_topological_sorting(): void
    {
        $graph = $this->builder([
            $this->module('Beta', GraphBetaModule::class),
            $this->module('Alpha', GraphAlphaModule::class),
        ])->build();

        self::assertSame(
            "Alpha -> Beta\nBeta -> Alpha",
            (new TextModuleGraphExporter)->export($graph),
        );
        self::assertSame([
            [
                'source' => GraphAlphaModule::class,
                'target' => GraphBetaModule::class,
                'evidence' => GraphAlphaModule::class.'::dependencies()',
            ],
            [
                'source' => GraphBetaModule::class,
                'target' => GraphAlphaModule::class,
                'evidence' => GraphBetaModule::class.'::dependencies()',
            ],
        ], array_map(
            static fn ($edge): array => $edge->toArray(),
            $graph->edges(),
        ));
    }

    public function test_module_neighborhood_contains_only_direct_incoming_and_outgoing_edges(): void
    {
        $graph = $this->builder([
            $this->module('Payment', GraphPaymentModule::class),
            $this->module('Inventory', GraphInventoryModule::class),
            $this->module('User', GraphUserModule::class),
            $this->module('Order', GraphOrderModule::class),
        ])->build()->neighborhood('user');

        self::assertSame(
            "Order -> User\nUser -> —",
            (new TextModuleGraphExporter)->export($graph),
        );
        self::assertSame(['Order', 'User'], array_map(
            static fn ($node): string => $node->name(),
            $graph->nodes(),
        ));
    }

    public function test_unknown_module_neighborhood_is_rejected(): void
    {
        $graph = $this->builder([
            $this->module('User', GraphUserModule::class),
        ])->build();

        $this->expectException(ModuleGraphFailed::class);
        $this->expectExceptionMessage('Module [Unknown] was not found in the dependency graph.');

        $graph->neighborhood('Unknown');
    }

    /**
     * @param list<DiscoveredModule> $modules
     */
    private function builder(array $modules): ModuleGraphBuilder
    {
        return new ModuleGraphBuilder(
            new ModuleRegistry($modules),
            new ModuleMetadataCompiler,
        );
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
}

final class GraphInventoryModule extends Module
{
}

final class GraphUserModule extends Module
{
}

final class GraphOrderModule extends Module
{
    /**
     * @return list<class-string<Module>>
     */
    public function dependencies(): array
    {
        return [GraphUserModule::class];
    }
}

final class GraphPaymentModule extends Module
{
    /**
     * @return list<class-string<Module>>
     */
    public function dependencies(): array
    {
        return [GraphOrderModule::class];
    }
}

final class GraphMissingModule extends Module
{
}

final class GraphMissingConsumerModule extends Module
{
    /**
     * @return list<class-string<Module>>
     */
    public function dependencies(): array
    {
        return [GraphMissingModule::class];
    }
}

final class GraphAlphaModule extends Module
{
    /**
     * @return list<class-string<Module>>
     */
    public function dependencies(): array
    {
        return [GraphBetaModule::class];
    }
}

final class GraphBetaModule extends Module
{
    /**
     * @return list<class-string<Module>>
     */
    public function dependencies(): array
    {
        return [GraphAlphaModule::class];
    }
}

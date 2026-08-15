<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Capabilities\CapabilityResolver;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Exceptions\CapabilityGraphFailed;
use Cluion\Moduark\Graph\CapabilityGraph;
use Cluion\Moduark\Graph\CapabilityGraphBuilder;
use Cluion\Moduark\Graph\Export\MermaidCapabilityGraphExporter;
use Cluion\Moduark\Graph\Export\TextCapabilityGraphExporter;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\LevelTwo\Modules\Checkout\CheckoutModule;
use Tests\Fixtures\LevelTwo\Modules\Order\OrderModule;
use Tests\Fixtures\LevelTwo\Modules\User\UserModule;

final class CapabilityGraphExporterTest extends TestCase
{
    public function test_it_exports_a_deterministic_text_view_with_isolated_modules(): void
    {
        self::assertSame(<<<'TEXT'
Checkout -[requires]-> UserLookup
Inventory -> —
Order -[requires]-> UserLookup
User -[provides]-> UserLookup
TEXT, (new TextCapabilityGraphExporter)->export($this->graph()));
    }

    public function test_it_exports_a_deterministic_mermaid_view(): void
    {
        self::assertSame(<<<'MERMAID'
flowchart LR
    M0["Checkout"]
    M1["Inventory"]
    M2["Order"]
    M3["User"]
    C0(["UserLookup"])
    M3 -->|"provides"| C0
    M0 -->|"requires"| C0
    M2 -->|"requires"| C0
MERMAID, (new MermaidCapabilityGraphExporter)->export($this->graph()));
    }

    public function test_module_neighborhood_keeps_the_complete_capability_relationship(): void
    {
        $graph = $this->graph()->neighborhood('order');

        self::assertSame(<<<'TEXT'
Checkout -[requires]-> UserLookup
Order -[requires]-> UserLookup
User -[provides]-> UserLookup
TEXT, (new TextCapabilityGraphExporter)->export($graph));
    }

    public function test_isolated_module_neighborhood_remains_visible(): void
    {
        $graph = $this->graph()->neighborhood('inventory');

        self::assertSame(
            'Inventory -> —',
            (new TextCapabilityGraphExporter)->export($graph),
        );
    }

    public function test_unknown_module_neighborhood_is_rejected(): void
    {
        $this->expectException(CapabilityGraphFailed::class);
        $this->expectExceptionMessage(
            'Module [Unknown] was not found in the Capability graph.',
        );

        $this->graph()->neighborhood('Unknown');
    }

    private function graph(): CapabilityGraph
    {
        $modules = [
            $this->module('Order', OrderModule::class),
            $this->module('Inventory', CapabilityExporterInventoryModule::class),
            $this->module('User', UserModule::class),
            $this->module('Checkout', CheckoutModule::class),
        ];

        return (new CapabilityGraphBuilder(
            new ModuleRegistry($modules),
            new ModuleMetadataCompiler,
            new CapabilityResolver,
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

final class CapabilityExporterInventoryModule extends Module
{
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Capabilities\CapabilityResolver;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Graph\CapabilityGraphBuilder;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use Tests\TestCase;
use Tests\Fixtures\LevelTwo\Modules\Checkout\CheckoutModule;
use Tests\Fixtures\LevelTwo\Modules\Order\OrderModule;
use Tests\Fixtures\LevelTwo\Modules\User\UserModule;

final class ModuleGraphCommandTest extends TestCase
{
    public function test_it_displays_the_module_dependency_graph_as_text(): void
    {
        $this->expectWorkbenchTextGraph('module:graph');
    }

    public function test_it_displays_the_module_dependency_graph_as_mermaid(): void
    {
        $this->command('module:graph --format=mermaid')
            ->expectsOutputToContain('flowchart LR')
            ->expectsOutputToContain('M0["Order"]')
            ->expectsOutputToContain('M1["User"]')
            ->expectsOutputToContain('M2["Workbench"]')
            ->expectsOutputToContain('M0 --> M1')
            ->assertSuccessful();
    }

    public function test_it_limits_output_to_a_module_and_direct_neighbors(): void
    {
        $this->command('module:graph User')
            ->expectsOutputToContain('Order -> User')
            ->expectsOutputToContain('User -> —')
            ->doesntExpectOutputToContain('Workbench')
            ->assertSuccessful();
    }

    public function test_unknown_module_is_a_tool_error(): void
    {
        $this->command('module:graph Unknown')
            ->expectsOutputToContain(
                'Module graph could not be generated: Module [Unknown] was not found in the dependency graph.',
            )
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    public function test_unknown_output_format_is_a_tool_error(): void
    {
        $this->command('module:graph --format=json')
            ->expectsOutputToContain('The --format option must be text or mermaid.')
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    public function test_it_displays_the_capability_graph_as_text(): void
    {
        $this->useCapabilityFixture();

        $this->command('module:graph --view=capability')
            ->expectsOutputToContain('Checkout -[requires]-> UserLookup')
            ->expectsOutputToContain('Inventory -> —')
            ->expectsOutputToContain('Order -[requires]-> UserLookup')
            ->expectsOutputToContain('User -[provides]-> UserLookup')
            ->assertSuccessful();
    }

    public function test_it_displays_the_capability_graph_as_mermaid(): void
    {
        $this->useCapabilityFixture();

        $this->command('module:graph --view=capability --format=mermaid')
            ->expectsOutputToContain('flowchart LR')
            ->expectsOutputToContain('C0(["UserLookup"])')
            ->expectsOutputToContain('M3 -->|"provides"| C0')
            ->expectsOutputToContain('M0 -->|"requires"| C0')
            ->assertSuccessful();
    }

    public function test_it_limits_the_capability_view_to_the_complete_module_neighborhood(): void
    {
        $this->useCapabilityFixture();

        $this->command('module:graph Order --view=capability')
            ->expectsOutputToContain('Checkout -[requires]-> UserLookup')
            ->expectsOutputToContain('Order -[requires]-> UserLookup')
            ->expectsOutputToContain('User -[provides]-> UserLookup')
            ->doesntExpectOutputToContain('Inventory')
            ->assertSuccessful();
    }

    public function test_unknown_capability_graph_module_is_a_tool_error(): void
    {
        $this->useCapabilityFixture();

        $this->command('module:graph Unknown --view=capability')
            ->expectsOutputToContain(
                'Module graph could not be generated: Module [Unknown] was not found in the Capability graph.',
            )
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    public function test_unknown_graph_view_is_a_tool_error(): void
    {
        $this->command('module:graph --view=combined')
            ->expectsOutputToContain('The --view option must be module or capability.')
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    public function test_it_displays_the_same_graph_after_config_cache(): void
    {
        try {
            $this->command('config:cache')->assertSuccessful();
            $this->refreshApplication();

            $this->expectWorkbenchTextGraph('module:graph');
        } finally {
            $this->command('config:clear')->run();
        }
    }

    private function expectWorkbenchTextGraph(string $command): void
    {
        $this->command($command)
            ->expectsOutputToContain('Order -> User')
            ->expectsOutputToContain('User -> —')
            ->expectsOutputToContain('Workbench -> —')
            ->assertSuccessful();
    }

    private function useCapabilityFixture(): void
    {
        $modules = [
            $this->module('Order', OrderModule::class),
            $this->module('Inventory', CapabilityGraphCommandInventoryModule::class),
            $this->module('User', UserModule::class),
            $this->module('Checkout', CheckoutModule::class),
        ];

        $this->application()->instance(
            CapabilityGraphBuilder::class,
            new CapabilityGraphBuilder(
                new ModuleRegistry($modules),
                new ModuleMetadataCompiler,
                new CapabilityResolver,
            ),
        );
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

final class CapabilityGraphCommandInventoryModule extends Module
{
}

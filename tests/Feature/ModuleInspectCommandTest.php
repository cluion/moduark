<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Analysis\Boundary\ConventionPublicApi;
use Cluion\Moduark\Analysis\Source\SourceIndexBuilder;
use Cluion\Moduark\Architecture\EffectiveArchitecture;
use Cluion\Moduark\Architecture\EffectiveRules;
use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Architecture\Level;
use Cluion\Moduark\Capabilities\CapabilityResolver;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Graph\CapabilityGraphBuilder;
use Cluion\Moduark\Graph\CombinedGraphBuilder;
use Cluion\Moduark\Graph\ModuleGraphBuilder;
use Cluion\Moduark\Inspection\ModuleInspectionBuilder;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use Tests\Fixtures\LevelTwo\Capabilities\UserLookup;
use Tests\Fixtures\LevelTwo\Modules\Order\Adapters\User\UserLookupAdapter;
use Tests\Fixtures\LevelTwo\Modules\Order\OrderModule;
use Tests\Fixtures\LevelTwo\Modules\Order\Ports\UserLookup as UserLookupPort;
use Tests\Fixtures\LevelTwo\Modules\User\UserModule;
use Tests\TestCase;

final class ModuleInspectCommandTest extends TestCase
{
    public function test_it_displays_detailed_module_metadata(): void
    {
        $root = dirname(__DIR__).'/Fixtures/LevelTwo/Modules';
        $modules = [
            $this->module('Order', OrderModule::class, $root),
            $this->module('User', UserModule::class, $root),
        ];
        $this->useInspectionFixture($modules);

        $this->command('module:inspect order')
            ->expectsTable(
                ['Field', 'Value'],
                [
                    ['Name', 'Order'],
                    ['Class', OrderModule::class],
                    ['Path', $root.'/Order/OrderModule.php'],
                    ['Namespace', 'Tests\\Fixtures\\LevelTwo\\Modules\\Order'],
                    ['State', 'enabled'],
                    ['Level', '2 (Decoupled)'],
                    ['Dependencies', 'User <'.UserModule::class.'> (discovered)'],
                    ['Missing dependencies', '—'],
                    ['Service providers', '—'],
                    [
                        'Requires',
                        'UserLookup <'.UserLookup::class.'>'
                        .' | Provider: User <'.UserModule::class.'>'
                        .' | Port: '.UserLookupPort::class
                        .' | Adapter: '.UserLookupAdapter::class,
                    ],
                    ['Provides', '—'],
                    ['Owned tables', "order_items\norders"],
                    ['Public API (convention)', OrderModule::class],
                ],
            )
            ->assertSuccessful();
    }

    public function test_unknown_module_is_a_tool_error(): void
    {
        $this->command('module:inspect Unknown')
            ->expectsOutputToContain(
                'Module inspection failed: Module [Unknown] was not found.',
            )
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    /**
     * @param class-string<Module> $moduleClass
     */
    private function module(string $name, string $moduleClass, string $root): DiscoveredModule
    {
        return new DiscoveredModule(
            $name,
            $moduleClass,
            "{$root}/{$name}/{$name}Module.php",
            "Tests\\Fixtures\\LevelTwo\\Modules\\{$name}",
        );
    }

    /** @param list<DiscoveredModule> $modules */
    private function useInspectionFixture(array $modules): void
    {
        $registry = new ModuleRegistry($modules);
        $compiler = new ModuleMetadataCompiler;
        $architecture = new EffectiveArchitecture(
            Level::Decoupled,
            Level::Decoupled,
            new EffectiveRules([]),
        );

        $this->application()->instance(
            ModuleInspectionBuilder::class,
            new ModuleInspectionBuilder(
                $registry,
                $compiler,
                new CombinedGraphBuilder(
                    new ModuleGraphBuilder($registry, $compiler),
                    new CapabilityGraphBuilder($registry, $compiler, new CapabilityResolver),
                ),
                new SourceIndexBuilder($registry),
                new ConventionPublicApi,
                $architecture,
            ),
        );
    }
}

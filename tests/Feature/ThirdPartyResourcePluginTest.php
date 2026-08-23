<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\ModuarkServiceProvider;
use Cluion\Moduark\Resources\ResourceDescriptor;
use Cluion\Moduark\Resources\ResourceDiscoverer;
use Cluion\Moduark\Resources\ResourceHandler;
use Cluion\Moduark\Resources\ResourceManifest;
use Cluion\Moduark\Resources\ResourcePhase;
use Cluion\Moduark\Resources\ResourcePlugin;
use Cluion\Moduark\Resources\ResourcePluginRegistration;
use Cluion\Moduark\Resources\ResourceRuntime;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use Tests\TestCase;
use Workbench\App\Modules\Order\OrderModule;

final class ThirdPartyResourcePluginTest extends TestCase
{
    /** @return list<class-string<ServiceProvider>> */
    protected function getPackageProviders($app): array
    {
        return [ModuarkServiceProvider::class, FixtureResourcePluginServiceProvider::class];
    }

    public function test_package_provider_can_register_discovery_and_runtime_handlers(): void
    {
        $manifest = $this->application()->make(ResourceManifest::class);
        $fixtureResources = array_values(array_filter(
            $manifest->forModule(OrderModule::class),
            static fn (ResourceDescriptor $resource): bool => $resource->plugin() === 'fixture',
        ));

        self::assertCount(1, $fixtureResources);
        self::assertSame('fixture-runtime', $fixtureResources[0]->identity());
        self::assertTrue(config('moduark.fixture_resource_handler_ran'));
    }
}

final class FixtureResourcePluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        ResourcePluginRegistration::register(
            $this->app,
            new ResourcePlugin(
                'fixture',
                new FixtureResourceDiscoverer,
                new FixtureResourceHandler,
            ),
        );
    }
}

final class FixtureResourceDiscoverer implements ResourceDiscoverer
{
    public function discover(
        DiscoveredModule $module,
        ModuleDescriptor $metadata,
        array $moduleConfiguration,
    ): array {
        return $module->moduleClass() === OrderModule::class
            ? [new ResourceDescriptor(OrderModule::class, 'fixture', 'fixture-runtime')]
            : [];
    }
}

final class FixtureResourceHandler implements ResourceHandler
{
    public function phase(): ResourcePhase
    {
        return ResourcePhase::Boot;
    }

    public function handle(ResourceDescriptor $resource, ResourceRuntime $runtime): void
    {
        $runtime->application()->make(Repository::class)->set(
            'moduark.fixture_resource_handler_ran',
            true,
        );
    }
}

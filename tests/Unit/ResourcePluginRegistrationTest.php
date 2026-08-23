<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Resources\ResourceDescriptor;
use Cluion\Moduark\Resources\ResourceDiscoverer;
use Cluion\Moduark\Resources\ResourceHandler;
use Cluion\Moduark\Resources\ResourcePhase;
use Cluion\Moduark\Resources\ResourcePlugin;
use Cluion\Moduark\Resources\ResourcePluginRegistration;
use Cluion\Moduark\Resources\ResourcePluginRegistry;
use Cluion\Moduark\Resources\ResourceRuntime;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

final class ResourcePluginRegistrationTest extends TestCase
{
    public function test_registration_is_independent_of_registry_resolution_order(): void
    {
        $early = new Container;
        ResourcePluginRegistration::register($early, $this->plugin('early'));
        $early->singleton(ResourcePluginRegistry::class);

        $late = new Container;
        $late->singleton(ResourcePluginRegistry::class);
        $late->make(ResourcePluginRegistry::class);
        ResourcePluginRegistration::register($late, $this->plugin('late'));

        self::assertSame(['early'], $early->make(ResourcePluginRegistry::class)->ids());
        self::assertSame(['late'], $late->make(ResourcePluginRegistry::class)->ids());
    }

    private function plugin(string $id): ResourcePlugin
    {
        return new ResourcePlugin(
            $id,
            new RegistrationResourceDiscoverer,
            new RegistrationResourceHandler,
        );
    }
}

final class RegistrationResourceDiscoverer implements ResourceDiscoverer
{
    public function discover(
        DiscoveredModule $module,
        ModuleDescriptor $metadata,
        array $moduleConfiguration,
    ): array {
        return [];
    }
}

final class RegistrationResourceHandler implements ResourceHandler
{
    public function phase(): ResourcePhase
    {
        return ResourcePhase::Boot;
    }

    public function handle(ResourceDescriptor $resource, ResourceRuntime $runtime): void
    {
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Package;

use Cluion\Moduark\Capabilities\CapabilityResolver;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Lifecycle\ModuleLifecycleRegistrar;
use Cluion\Moduark\Lifecycle\ModuleOrderer;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use Cluion\Moduark\Resources\BuiltInResourcePlugins;
use Cluion\Moduark\Resources\ResourceManifest;
use Cluion\Moduark\Resources\ResourceManifestBuilder;
use Cluion\Moduark\Resources\ResourceOwnership;
use Cluion\Moduark\Resources\ResourcePhase;
use Cluion\Moduark\Resources\ResourcePluginRegistry;
use Cluion\Moduark\Resources\ResourceRegistrationState;
use Cluion\Moduark\Resources\ResourceRuntime;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;
use RuntimeException;

abstract class PortableModuleServiceProvider extends ServiceProvider
{
    private bool $delegatedToCanonicalRuntime = false;

    private ?ResourceManifest $portableManifest = null;

    private ?ResourcePluginRegistry $portablePlugins = null;

    private ?ResourceRegistrationState $portableState = null;

    abstract protected function moduleClass(): string;

    abstract protected function modulePath(): string;

    public function register(): void
    {
        if ($this->canonicalDescriptorDeclared()) {
            $this->delegatedToCanonicalRuntime = true;

            return;
        }

        [$module, $compiler] = $this->portableModule();
        $application = $this->app->make(Application::class);
        $ordered = (new ModuleLifecycleRegistrar(
            $this->app,
            $compiler,
            new ModuleOrderer,
            new CapabilityResolver,
        ))->registerProviders([$module->moduleClass()]);
        $plugins = $this->plugins();

        $this->app->singleton(
            $module->moduleClass(),
            static fn (): Module => new ($module->moduleClass()),
        );
        $this->portableManifest = (new ResourceManifestBuilder($plugins))->build(
            new ModuleRegistry([$module]),
            $ordered,
        );
        $this->portablePlugins = $plugins;
        $this->portableState = new ResourceRegistrationState;
        $this->handle(ResourcePhase::Register, $application);
    }

    public function boot(): void
    {
        if ($this->delegatedToCanonicalRuntime) {
            return;
        }

        $this->handle(ResourcePhase::Boot, $this->app->make(Application::class));
    }

    /** @return array{DiscoveredModule, ModuleMetadataCompiler} */
    private function portableModule(): array
    {
        $moduleClass = $this->moduleClass();
        $modulePath = $this->modulePath();

        if (! is_a($moduleClass, Module::class, true)) {
            throw new RuntimeException("Portable Module class [{$moduleClass}] is invalid.");
        }

        $reflection = new ReflectionClass($moduleClass);
        $autoloadedPath = $reflection->getFileName();
        $expectedPath = realpath($modulePath);
        $actualPath = is_string($autoloadedPath) ? realpath($autoloadedPath) : false;

        if ($expectedPath === false || $actualPath !== $expectedPath) {
            throw new RuntimeException("Portable Module source [{$modulePath}] does not match [{$moduleClass}].");
        }

        $shortName = $reflection->getShortName();

        if (! str_ends_with($shortName, 'Module') || $shortName === 'Module') {
            throw new RuntimeException("Portable Module class [{$moduleClass}] must end with Module.");
        }

        $name = substr($shortName, 0, -strlen('Module'));
        $module = new DiscoveredModule(
            $name,
            $moduleClass,
            $modulePath,
            $reflection->getNamespaceName(),
        );

        return [$module, new ModuleMetadataCompiler];
    }

    private function plugins(): ResourcePluginRegistry
    {
        if ($this->app->bound(ResourcePluginRegistry::class)) {
            return $this->app->make(ResourcePluginRegistry::class);
        }

        $plugins = new ResourcePluginRegistry;
        BuiltInResourcePlugins::register($plugins);

        return $plugins;
    }

    private function canonicalDescriptorDeclared(): bool
    {
        $moduleClass = $this->moduleClass();

        if (! is_a($moduleClass, Module::class, true)) {
            return false;
        }

        $discoverer = $this->app->bound(ComposerPackageModuleDiscoverer::class)
            ? $this->app->make(ComposerPackageModuleDiscoverer::class)
            : ComposerPackageModuleDiscoverer::fromComposerRuntime();

        return $discoverer->discover()->containsClass($moduleClass);
    }

    private function handle(ResourcePhase $phase, Application $application): void
    {
        if ($this->portableManifest === null
            || $this->portablePlugins === null
            || $this->portableState === null) {
            throw new RuntimeException('Portable Module runtime was not registered before boot.');
        }

        $runtime = new ResourceRuntime(
            $application,
            false,
            new ResourceOwnership(false),
        );

        foreach ($this->portableManifest->all() as $resource) {
            $handler = $this->portablePlugins->get($resource->plugin())->handler();

            if ($handler->phase() === $phase && $this->portableState->claim($phase, $resource)) {
                $handler->handle($resource, $runtime);
            }
        }
    }
}

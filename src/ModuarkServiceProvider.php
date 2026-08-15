<?php

declare(strict_types=1);

namespace Cluion\Moduark;

use Cluion\Moduark\Architecture\EffectiveArchitecture;
use Cluion\Moduark\Architecture\RulePresets;
use Cluion\Moduark\Architecture\RuleResolver;
use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Console\MakeModuleCommand;
use Cluion\Moduark\Console\ModuleListCommand;
use Cluion\Moduark\Discovery\ModuleDiscoverer;
use Cluion\Moduark\Lifecycle\ModuleLifecycleRegistrar;
use Cluion\Moduark\Lifecycle\ModuleOrderer;
use Cluion\Moduark\Lifecycle\OrderedModules;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Registry\ModuleRegistry;
use Cluion\Moduark\Resources\ModuleResourceDiscoverer;
use Cluion\Moduark\Resources\ModuleResourceServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class ModuarkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $path = dirname(__DIR__).'/config/modules.php';
        $defaults = require $path;

        if (! is_array($defaults)) {
            throw new InvalidArgumentException('The Moduark default configuration must return an array.');
        }

        $this->mergeConfigFrom($path, 'modules');

        /** @var Repository $repository */
        $repository = $this->app->make(Repository::class);
        $configured = $repository->get('modules', []);

        if (! is_array($configured)) {
            throw new InvalidArgumentException('The modules configuration must be an array.');
        }

        $configuration = ModulesConfig::from($defaults, $configured);

        $repository->set('modules', $configuration->all());
        $this->app->instance(ModulesConfig::class, $configuration);
        $this->app->singleton(RulePresets::class);
        $this->app->singleton(RuleResolver::class);
        $this->app->instance(
            EffectiveArchitecture::class,
            $this->app->make(RuleResolver::class)->resolve($configuration),
        );
        $this->app->singleton(ModuleDiscoverer::class);
        $this->app->singleton(
            ModuleRegistry::class,
            fn (): ModuleRegistry => $this->app->make(ModuleDiscoverer::class)
                ->discover($this->app->make(ModulesConfig::class)->path()),
        );
        $this->app->singleton(ModuleMetadataCompiler::class);
        $this->app->singleton(ModuleOrderer::class);
        $this->app->singleton(ModuleLifecycleRegistrar::class);
        $this->app->singleton(ModuleResourceDiscoverer::class);

        $registry = $this->app->make(ModuleRegistry::class);
        $ordered = $this->app->make(ModuleLifecycleRegistrar::class)
            ->registerProviders($registry->moduleClasses());

        $this->app->instance(OrderedModules::class, new OrderedModules($ordered));
        $this->app->register(ModuleResourceServiceProvider::class);
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            MakeModuleCommand::class,
            ModuleListCommand::class,
        ]);

        $this->publishes([
            dirname(__DIR__).'/config/modules.php' => config_path('modules.php'),
        ], 'moduark-config');

        $this->publishes([
            dirname(__DIR__).'/stubs/module.stub' => base_path('stubs/module.stub'),
        ], 'moduark-stubs');
    }
}

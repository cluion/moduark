<?php

declare(strict_types=1);

namespace Cluion\Moduark\Resources;

use Cluion\Moduark\Lifecycle\OrderedModules;
use Cluion\Moduark\Registry\ModuleRegistry;
use Illuminate\Support\ServiceProvider;
use LogicException;

final class ModuleResourceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $modules = [];

        foreach ($this->app->make(ModuleRegistry::class)->all() as $module) {
            $modules[$module->moduleClass()] = $module;
        }

        $includeCommands = $this->app->runningInConsole();

        foreach ($this->app->make(OrderedModules::class)->all() as $descriptor) {
            $moduleClass = $descriptor->moduleClass();
            $module = $modules[$moduleClass] ?? null;

            if ($module === null) {
                throw new LogicException("Ordered Module [{$moduleClass}] is missing from the registry.");
            }

            $this->bootResources(
                $this->app->make(ModuleResourceDiscoverer::class)->discover(
                    $module,
                    $includeCommands,
                ),
            );
        }
    }

    private function bootResources(ModuleResources $resources): void
    {
        foreach ($resources->routePaths() as $routePath) {
            $this->loadRoutesFrom($routePath);
        }

        if ($resources->viewPath() !== null) {
            $this->loadViewsFrom($resources->viewPath(), $resources->namespace());
        }

        if ($resources->translationPath() !== null) {
            $this->loadTranslationsFrom($resources->translationPath(), $resources->namespace());
        }

        if ($resources->migrationPath() !== null) {
            $this->loadMigrationsFrom($resources->migrationPath());
        }

        if ($this->app->runningInConsole() && $resources->commands() !== []) {
            $this->commands($resources->commands());
        }
    }
}

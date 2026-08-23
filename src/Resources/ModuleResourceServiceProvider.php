<?php

declare(strict_types=1);

namespace Cluion\Moduark\Resources;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Application;

final class ModuleResourceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->handle(ResourcePhase::Register);
    }

    public function boot(): void
    {
        $this->handle(ResourcePhase::Boot);
    }

    private function handle(ResourcePhase $phase): void
    {
        $plugins = $this->app->make(ResourcePluginRegistry::class);
        $status = $this->app->make(ResourceManifestStatus::class);
        $state = $this->app->make(ResourceRegistrationState::class);
        $runtime = new ResourceRuntime(
            $this->app->make(Application::class),
            $status->cached(),
            $this->app->make(ResourceOwnership::class),
        );

        foreach ($this->app->make(ResourceManifest::class)->all() as $resource) {
            $handler = $plugins->get($resource->plugin())->handler();

            if ($handler->phase() === $phase && $state->claim($phase, $resource)) {
                $handler->handle($resource, $runtime);
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Resources;

use Cluion\Moduark\Exceptions\ResourceManifestFailed;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class ResourceRuntime extends ServiceProvider
{
    public function __construct(
        private Application $runtimeApplication,
        private bool $cached,
        private ResourceOwnership $ownership,
    ) {
        parent::__construct($this->runtimeApplication);
    }

    public function register(): void
    {
    }

    public function boot(): void
    {
    }

    public function application(): Application
    {
        return $this->runtimeApplication;
    }

    public function cached(): bool
    {
        return $this->cached;
    }

    public function runningInConsole(): bool
    {
        return $this->runtimeApplication->runningInConsole();
    }

    public function routesAreCached(): bool
    {
        return $this->runtimeApplication->routesAreCached();
    }

    public function conventionsManagedExternally(): bool
    {
        return $this->ownership->conventionsManagedExternally();
    }

    /** @param array<string, mixed> $values */
    public function mergeConfiguration(array $values, string $key, string $moduleClass): void
    {
        $repository = $this->runtimeApplication->make(Repository::class);
        $existing = $repository->get($key, []);

        if (! is_array($existing)) {
            throw ResourceManifestFailed::invalidConfiguration($moduleClass, 'config');
        }

        $repository->set($key, array_replace_recursive($values, $existing));
    }

    public function publishResource(string $source, string $destination, string $group): void
    {
        $this->publishes([$source => $destination], $group);
    }
}

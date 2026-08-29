<?php

declare(strict_types=1);

namespace Tests\Fixtures\PackageRuntime\PackageInventory\Providers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;

final class PackageInventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $configuration = $this->app->make(Repository::class);
        $count = $configuration->get('package_inventory.provider.register_count', 0);
        $configuration->set(
            'package_inventory.provider.register_count',
            is_int($count) ? $count + 1 : 1,
        );
    }

    public function boot(): void
    {
        $configuration = $this->app->make(Repository::class);
        $count = $configuration->get('package_inventory.provider.boot_count', 0);
        $configuration->set(
            'package_inventory.provider.boot_count',
            is_int($count) ? $count + 1 : 1,
        );
    }
}

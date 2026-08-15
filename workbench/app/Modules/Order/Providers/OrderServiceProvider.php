<?php

declare(strict_types=1);

namespace Workbench\App\Modules\Order\Providers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;

final class OrderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(Repository::class)->set(
            'moduark.order.provider.registered',
            true,
        );
    }

    public function boot(): void
    {
        $this->app->make(Repository::class)->set(
            'moduark.order.provider.booted',
            true,
        );
    }
}

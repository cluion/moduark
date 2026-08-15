<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Support\ServiceProvider;

final class RouteLoadingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }
}

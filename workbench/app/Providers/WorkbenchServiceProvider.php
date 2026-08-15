<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;

final class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance('moduark.workbench.loaded', true);
    }
}

<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Cluion\Moduark\Metadata\ModuleDescriptor;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use Workbench\App\Modules\WorkbenchModule;

final class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance('moduark.workbench.loaded', true);

        $configuration = $this->app->make(Repository::class);

        if ($configuration->has('moduark.workbench.descriptors')) {
            return;
        }

        $descriptor = new ModuleDescriptor(
            WorkbenchModule::class,
            [],
            [self::class],
        );

        $configuration->set('moduark.workbench.descriptors', [$descriptor->toArray()]);
    }
}

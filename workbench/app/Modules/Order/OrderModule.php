<?php

declare(strict_types=1);

namespace Workbench\App\Modules\Order;

use Cluion\Moduark\Module;
use Illuminate\Support\ServiceProvider;
use Workbench\App\Modules\Order\Providers\OrderServiceProvider;
use Workbench\App\Modules\User\UserModule;

final class OrderModule extends Module
{
    /**
     * @return list<class-string<Module>>
     */
    public function dependencies(): array
    {
        return [UserModule::class];
    }

    /**
     * @return list<class-string<ServiceProvider>>
     */
    public function providers(): array
    {
        return [OrderServiceProvider::class];
    }

    /**
     * @return list<string>
     */
    public function tables(): array
    {
        return ['moduark_orders'];
    }
}

<?php

declare(strict_types=1);

namespace Workbench\App\Modules\Order;

use Cluion\Moduark\Module;
use Illuminate\Support\ServiceProvider;
use Workbench\App\Modules\Order\Database\Seeders\OrderDatabaseSeeder;
use Workbench\App\Modules\Order\Events\OrderProbed;
use Workbench\App\Modules\Order\Listeners\RecordOrderProbe;
use Workbench\App\Modules\Order\Models\OrderRecord;
use Workbench\App\Modules\Order\Policies\OrderPolicy;
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

    public function resources(): array
    {
        return [
            'routes' => [
                [
                    'path' => 'routes/admin.php',
                    'group' => ['prefix' => '__moduark-order-admin'],
                ],
            ],
            'config' => [
                [
                    'path' => 'config/order.php',
                    'key' => 'order-module',
                    'publish' => true,
                ],
            ],
            'commands' => ['recursive' => true],
            'factories' => true,
            'seeders' => [OrderDatabaseSeeder::class],
            'policies' => [OrderRecord::class => OrderPolicy::class],
            'listeners' => [OrderProbed::class => [RecordOrderProbe::class]],
            'components' => true,
            'assets' => [
                'resources/js/order.js',
                'resources/css/order.css',
                [
                    'path' => 'resources/public/order-icon.svg',
                    'type' => 'public',
                    'publish_to' => 'vendor/order/order-icon.svg',
                ],
            ],
            'tests' => true,
            'extensions' => [
                'frontend' => [
                    'driver' => 'vite',
                    'framework' => 'generic',
                ],
            ],
        ];
    }
}

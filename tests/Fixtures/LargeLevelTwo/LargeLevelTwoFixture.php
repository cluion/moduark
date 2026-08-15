<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo;

use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use Tests\Fixtures\LargeLevelTwo\Modules\Catalog\CatalogModule;
use Tests\Fixtures\LargeLevelTwo\Modules\Checkout\CheckoutModule;
use Tests\Fixtures\LargeLevelTwo\Modules\Customer\CustomerModule;
use Tests\Fixtures\LargeLevelTwo\Modules\Fulfillment\FulfillmentModule;
use Tests\Fixtures\LargeLevelTwo\Modules\Inventory\InventoryModule;
use Tests\Fixtures\LargeLevelTwo\Modules\Notification\NotificationModule;
use Tests\Fixtures\LargeLevelTwo\Modules\Payment\PaymentModule;
use Tests\Fixtures\LargeLevelTwo\Modules\Returns\ReturnsModule;

final class LargeLevelTwoFixture
{
    /** @return list<DiscoveredModule> */
    public static function modules(): array
    {
        return [
            self::module('Returns', ReturnsModule::class),
            self::module('Customer', CustomerModule::class),
            self::module('Checkout', CheckoutModule::class),
            self::module('Inventory', InventoryModule::class),
            self::module('Catalog', CatalogModule::class),
            self::module('Fulfillment', FulfillmentModule::class),
            self::module('Notification', NotificationModule::class),
            self::module('Payment', PaymentModule::class),
        ];
    }

    public static function registry(): ModuleRegistry
    {
        return new ModuleRegistry(self::modules());
    }

    /** @return list<class-string<Module>> */
    public static function moduleClasses(): array
    {
        return array_map(
            static fn (DiscoveredModule $module): string => $module->moduleClass(),
            self::modules(),
        );
    }

    /** @return list<class-string> */
    public static function ports(): array
    {
        return [
            Modules\Checkout\Ports\CustomerLookup::class,
            Modules\Checkout\Ports\ProductCatalog::class,
            Modules\Checkout\Ports\StockAvailability::class,
            Modules\Checkout\Ports\PaymentAuthorization::class,
            Modules\Fulfillment\Ports\CustomerLookup::class,
            Modules\Fulfillment\Ports\StockAvailability::class,
            Modules\Fulfillment\Ports\NotificationDelivery::class,
            Modules\Returns\Ports\CustomerLookup::class,
            Modules\Returns\Ports\ProductCatalog::class,
            Modules\Returns\Ports\StockAvailability::class,
            Modules\Returns\Ports\PaymentAuthorization::class,
            Modules\Returns\Ports\NotificationDelivery::class,
        ];
    }

    public static function configuration(): ModulesConfig
    {
        return ModulesConfig::from([
            'path' => self::root().'/Modules',
            'architecture' => [
                'level' => 2,
                'rules' => [],
            ],
        ], []);
    }

    /**
     * @param class-string<Module> $moduleClass
     */
    private static function module(string $name, string $moduleClass): DiscoveredModule
    {
        return new DiscoveredModule(
            $name,
            $moduleClass,
            self::root()."/Modules/{$name}/{$name}Module.php",
            "Tests\\Fixtures\\LargeLevelTwo\\Modules\\{$name}",
        );
    }

    private static function root(): string
    {
        return __DIR__;
    }
}

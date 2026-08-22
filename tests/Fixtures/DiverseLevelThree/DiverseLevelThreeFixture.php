<?php

declare(strict_types=1);

namespace Tests\Fixtures\DiverseLevelThree;

use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use Tests\Fixtures\DiverseLevelThree\Modules\Customer\CustomerModule;
use Tests\Fixtures\DiverseLevelThree\Modules\Order\OrderModule;
use Tests\Fixtures\DiverseLevelThree\Modules\Payment\PaymentModule;

final class DiverseLevelThreeFixture
{
    /** @return list<DiscoveredModule> */
    public static function modules(): array
    {
        return [
            self::module('Payment', PaymentModule::class),
            self::module('Order', OrderModule::class),
            self::module('Customer', CustomerModule::class),
        ];
    }

    public static function registry(): ModuleRegistry
    {
        return new ModuleRegistry(self::modules());
    }

    /** @return list<class-string<Module>> */
    public static function moduleClasses(): array
    {
        return self::registry()->moduleClasses();
    }

    /** @return list<class-string> */
    public static function ports(): array
    {
        return [
            Modules\Order\Ports\CustomerLookup::class,
            Modules\Order\Ports\PaymentAuthorization::class,
        ];
    }

    public static function configuration(): ModulesConfig
    {
        return ModulesConfig::from([
            'path' => self::root().'/Modules',
            'architecture' => [
                'level' => 3,
                'rules' => [],
            ],
        ], []);
    }

    /** @param class-string<Module> $moduleClass */
    private static function module(string $name, string $moduleClass): DiscoveredModule
    {
        return new DiscoveredModule(
            $name,
            $moduleClass,
            self::root()."/Modules/{$name}/{$name}Module.php",
            "Tests\\Fixtures\\DiverseLevelThree\\Modules\\{$name}",
        );
    }

    private static function root(): string
    {
        return __DIR__;
    }
}

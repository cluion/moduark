<?php

declare(strict_types=1);

namespace Cluion\Moduark\Resources;

use Illuminate\Contracts\Container\Container;

final class ResourcePluginRegistration
{
    public static function register(Container $container, ResourcePlugin $plugin): void
    {
        $container->extend(
            ResourcePluginRegistry::class,
            static function (ResourcePluginRegistry $registry) use ($plugin): ResourcePluginRegistry {
                $registry->register($plugin);

                return $registry;
            },
        );
    }

    private function __construct()
    {
    }
}

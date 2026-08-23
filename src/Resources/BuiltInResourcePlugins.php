<?php

declare(strict_types=1);

namespace Cluion\Moduark\Resources;

final class BuiltInResourcePlugins
{
    public static function register(
        ResourcePluginRegistry $registry,
    ): void {
        $scanner = new CommandResourceScanner;

        foreach ([
            'routes',
            'config',
            'views',
            'translations',
            'migrations',
            'commands',
            'factories',
            'seeders',
            'policies',
            'events',
            'listeners',
            'components',
            'providers',
            'assets',
            'tests',
            'extensions',
        ] as $id) {
            $registry->register(new ResourcePlugin(
                $id,
                new BuiltInResourceDiscoverer($id, $scanner),
                new BuiltInResourceHandler($id),
            ));
        }
    }

    private function __construct()
    {
    }
}

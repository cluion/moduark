<?php

declare(strict_types=1);

namespace Tests\Fixtures\PackageRuntime\PackageInventory;

use Cluion\Moduark\Package\PortableModuleServiceProvider;

final class PackageInventoryPackageServiceProvider extends PortableModuleServiceProvider
{
    protected function moduleClass(): string
    {
        return PackageInventoryModule::class;
    }

    protected function modulePath(): string
    {
        return __DIR__.'/PackageInventoryModule.php';
    }
}

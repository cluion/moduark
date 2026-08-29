<?php

declare(strict_types=1);

namespace Tests\Fixtures\PackageRuntime\PackageInventory;

use Cluion\Moduark\CapabilityRequirement;
use Cluion\Moduark\Module;
use Illuminate\Support\ServiceProvider;
use Tests\Fixtures\PackageRuntime\PackageInventory\Capabilities\PackageInventoryCapability;
use Tests\Fixtures\PackageRuntime\PackageInventory\Capabilities\PackageInventoryPort;
use Tests\Fixtures\PackageRuntime\PackageInventory\Capabilities\PackageInventoryAdapter;
use Tests\Fixtures\PackageRuntime\PackageInventory\Providers\PackageInventoryServiceProvider;

final class PackageInventoryModule extends Module
{
    /** @return list<class-string<ServiceProvider>> */
    public function providers(): array
    {
        return [PackageInventoryServiceProvider::class];
    }

    /** @return list<CapabilityRequirement> */
    public function requires(): array
    {
        return [new CapabilityRequirement(
            PackageInventoryCapability::class,
            PackageInventoryPort::class,
            PackageInventoryAdapter::class,
        )];
    }

    public function provides(): array
    {
        return [PackageInventoryCapability::class];
    }

    public function resources(): array
    {
        return [
            'config' => [[
                'path' => 'config/package-inventory.php',
                'key' => 'package_inventory',
            ]],
        ];
    }
}

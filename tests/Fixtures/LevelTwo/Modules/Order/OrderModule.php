<?php

declare(strict_types=1);

namespace Tests\Fixtures\LevelTwo\Modules\Order;

use Cluion\Moduark\Module;
use Tests\Fixtures\LevelTwo\Capabilities\UserLookup as UserLookupCapability;
use Tests\Fixtures\LevelTwo\Modules\Order\Adapters\User\UserLookupAdapter;
use Tests\Fixtures\LevelTwo\Modules\Order\Ports\UserLookup as UserLookupPort;
use Tests\Fixtures\LevelTwo\Modules\User\UserModule;
use Tests\Fixtures\LevelTwo\Support\Capability;
use Tests\Fixtures\LevelTwo\Support\CapabilityMetadata;
use Tests\Fixtures\LevelTwo\Support\CapabilityRequirement;

final class OrderModule extends Module implements CapabilityMetadata
{
    /** @return list<class-string<Module>> */
    public function dependencies(): array
    {
        return [UserModule::class];
    }

    /** @return list<class-string<Capability>> */
    public function provides(): array
    {
        return [];
    }

    /** @return list<CapabilityRequirement> */
    public function requires(): array
    {
        return [new CapabilityRequirement(
            UserLookupCapability::class,
            UserLookupPort::class,
            UserLookupAdapter::class,
        )];
    }
}

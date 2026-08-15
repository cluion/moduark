<?php

declare(strict_types=1);

namespace Tests\Fixtures\LevelTwo\Modules\Order;

use Cluion\Moduark\Capability;
use Cluion\Moduark\CapabilityRequirement;
use Cluion\Moduark\Module;
use Tests\Fixtures\LevelTwo\Capabilities\UserLookup as UserLookupCapability;
use Tests\Fixtures\LevelTwo\Modules\Order\Adapters\User\UserLookupAdapter;
use Tests\Fixtures\LevelTwo\Modules\Order\Ports\UserLookup as UserLookupPort;
use Tests\Fixtures\LevelTwo\Modules\User\UserModule;

final class OrderModule extends Module
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

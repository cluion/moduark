<?php

declare(strict_types=1);

namespace Tests\Fixtures\LevelTwo\Modules\User;

use Cluion\Moduark\Capability;
use Cluion\Moduark\CapabilityRequirement;
use Cluion\Moduark\Module;
use Illuminate\Support\ServiceProvider;
use Tests\Fixtures\LevelTwo\Capabilities\UserLookup;
use Tests\Fixtures\LevelTwo\Modules\User\Providers\UserServiceProvider;

final class UserModule extends Module
{
    /** @return list<class-string<ServiceProvider>> */
    public function providers(): array
    {
        return [UserServiceProvider::class];
    }

    /** @return list<class-string<Capability>> */
    public function provides(): array
    {
        return [UserLookup::class];
    }

    /** @return list<CapabilityRequirement> */
    public function requires(): array
    {
        return [];
    }
}

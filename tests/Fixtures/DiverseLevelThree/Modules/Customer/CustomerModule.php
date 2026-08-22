<?php

declare(strict_types=1);

namespace Tests\Fixtures\DiverseLevelThree\Modules\Customer;

use Cluion\Moduark\Capability;
use Cluion\Moduark\Module;
use Tests\Fixtures\DiverseLevelThree\Capabilities\CustomerLookup;
use Tests\Fixtures\DiverseLevelThree\Modules\Customer\Contracts\CustomerDirectory;

final class CustomerModule extends Module
{
    /** @return list<class-string<Capability>> */
    public function provides(): array
    {
        return [CustomerLookup::class];
    }

    public function tables(): array
    {
        return ['customers', 'customer_profiles'];
    }

    public function exports(): array
    {
        return [CustomerDirectory::class];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Customer;

use Cluion\Moduark\Capability;
use Cluion\Moduark\Module;
use Tests\Fixtures\LargeLevelTwo\Capabilities\CustomerLookup;

final class CustomerModule extends Module
{
    /** @return list<class-string<Capability>> */
    public function provides(): array
    {
        return [CustomerLookup::class];
    }
}

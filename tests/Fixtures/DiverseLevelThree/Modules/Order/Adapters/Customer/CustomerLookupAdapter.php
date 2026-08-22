<?php

declare(strict_types=1);

namespace Tests\Fixtures\DiverseLevelThree\Modules\Order\Adapters\Customer;

use Tests\Fixtures\DiverseLevelThree\Modules\Customer\Contracts\CustomerDirectory;
use Tests\Fixtures\DiverseLevelThree\Modules\Order\Ports\CustomerLookup;

final readonly class CustomerLookupAdapter implements CustomerLookup
{
    public function __construct(private CustomerDirectory $customers)
    {
    }

    public function customerName(int $customerId): string
    {
        return $this->customers->name($customerId);
    }
}

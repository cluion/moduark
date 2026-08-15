<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Fulfillment\Adapters\Customer;

use Tests\Fixtures\LargeLevelTwo\Modules\Fulfillment\Ports\CustomerLookup;
use Tests\Fixtures\LargeLevelTwo\Modules\Customer\Contracts\CustomerDirectory;

final readonly class CustomerLookupAdapter implements CustomerLookup
{
    public function __construct(private CustomerDirectory $service)
    {
    }

    public function customerName(int $customerId): string
    {
        return $this->service->name($customerId);
    }
}

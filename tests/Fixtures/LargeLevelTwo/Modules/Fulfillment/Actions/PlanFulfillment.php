<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Fulfillment\Actions;

use Tests\Fixtures\LargeLevelTwo\Modules\Fulfillment\Ports\CustomerLookup;
use Tests\Fixtures\LargeLevelTwo\Modules\Fulfillment\Ports\NotificationDelivery;
use Tests\Fixtures\LargeLevelTwo\Modules\Fulfillment\Ports\StockAvailability;

final readonly class PlanFulfillment
{
    public function __construct(
        private CustomerLookup $customers,
        private StockAvailability $stock,
        private NotificationDelivery $notifications,
    ) {
    }

    public function for(int $customerId, string $sku): string
    {
        return implode(' | ', [
            $this->customers->customerName($customerId),
            $this->stock->inStock($sku) ? 'in stock' : 'out of stock',
            $this->notifications->notify('fulfillment queued'),
        ]);
    }
}

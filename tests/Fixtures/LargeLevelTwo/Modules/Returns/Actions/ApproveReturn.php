<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Returns\Actions;

use Tests\Fixtures\LargeLevelTwo\Modules\Returns\Ports\CustomerLookup;
use Tests\Fixtures\LargeLevelTwo\Modules\Returns\Ports\NotificationDelivery;
use Tests\Fixtures\LargeLevelTwo\Modules\Returns\Ports\PaymentAuthorization;
use Tests\Fixtures\LargeLevelTwo\Modules\Returns\Ports\ProductCatalog;
use Tests\Fixtures\LargeLevelTwo\Modules\Returns\Ports\StockAvailability;

final readonly class ApproveReturn
{
    public function __construct(
        private CustomerLookup $customers,
        private ProductCatalog $products,
        private StockAvailability $stock,
        private PaymentAuthorization $payments,
        private NotificationDelivery $notifications,
    ) {
    }

    public function for(int $customerId, string $sku): string
    {
        return implode(' | ', [
            $this->customers->customerName($customerId),
            $this->products->productLabel($sku),
            $this->stock->inStock($sku) ? 'in stock' : 'out of stock',
            $this->payments->authorized($customerId, $sku) ? 'authorized' : 'declined',
            $this->notifications->notify('return approved'),
        ]);
    }
}

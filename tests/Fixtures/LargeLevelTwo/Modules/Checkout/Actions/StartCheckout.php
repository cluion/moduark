<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Checkout\Actions;

use Tests\Fixtures\LargeLevelTwo\Modules\Checkout\Ports\CustomerLookup;
use Tests\Fixtures\LargeLevelTwo\Modules\Checkout\Ports\PaymentAuthorization;
use Tests\Fixtures\LargeLevelTwo\Modules\Checkout\Ports\ProductCatalog;
use Tests\Fixtures\LargeLevelTwo\Modules\Checkout\Ports\StockAvailability;

final readonly class StartCheckout
{
    public function __construct(
        private CustomerLookup $customers,
        private ProductCatalog $products,
        private StockAvailability $stock,
        private PaymentAuthorization $payments,
    ) {
    }

    public function for(int $customerId, string $sku): string
    {
        return implode(' | ', [
            $this->customers->customerName($customerId),
            $this->products->productLabel($sku),
            $this->stock->inStock($sku) ? 'in stock' : 'out of stock',
            $this->payments->authorized($customerId, $sku) ? 'authorized' : 'declined',
        ]);
    }
}

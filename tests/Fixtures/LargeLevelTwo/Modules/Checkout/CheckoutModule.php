<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Checkout;

use Cluion\Moduark\CapabilityRequirement;
use Cluion\Moduark\Module;
use Tests\Fixtures\LargeLevelTwo\Capabilities\CustomerLookup as CustomerLookupCapability;
use Tests\Fixtures\LargeLevelTwo\Capabilities\PaymentAuthorization as PaymentAuthorizationCapability;
use Tests\Fixtures\LargeLevelTwo\Capabilities\ProductCatalog as ProductCatalogCapability;
use Tests\Fixtures\LargeLevelTwo\Capabilities\StockAvailability as StockAvailabilityCapability;
use Tests\Fixtures\LargeLevelTwo\Modules\Catalog\CatalogModule;
use Tests\Fixtures\LargeLevelTwo\Modules\Checkout\Adapters\Catalog\ProductCatalogAdapter;
use Tests\Fixtures\LargeLevelTwo\Modules\Checkout\Adapters\Customer\CustomerLookupAdapter;
use Tests\Fixtures\LargeLevelTwo\Modules\Checkout\Adapters\Inventory\StockAvailabilityAdapter;
use Tests\Fixtures\LargeLevelTwo\Modules\Checkout\Adapters\Payment\PaymentAuthorizationAdapter;
use Tests\Fixtures\LargeLevelTwo\Modules\Checkout\Ports\CustomerLookup as CustomerLookupPort;
use Tests\Fixtures\LargeLevelTwo\Modules\Checkout\Ports\PaymentAuthorization as PaymentAuthorizationPort;
use Tests\Fixtures\LargeLevelTwo\Modules\Checkout\Ports\ProductCatalog as ProductCatalogPort;
use Tests\Fixtures\LargeLevelTwo\Modules\Checkout\Ports\StockAvailability as StockAvailabilityPort;
use Tests\Fixtures\LargeLevelTwo\Modules\Customer\CustomerModule;
use Tests\Fixtures\LargeLevelTwo\Modules\Inventory\InventoryModule;
use Tests\Fixtures\LargeLevelTwo\Modules\Payment\PaymentModule;

final class CheckoutModule extends Module
{
    /** @return list<class-string<Module>> */
    public function dependencies(): array
    {
        return [
            CatalogModule::class,
            CustomerModule::class,
            InventoryModule::class,
            PaymentModule::class,
        ];
    }

    /** @return list<CapabilityRequirement> */
    public function requires(): array
    {
        return [
            new CapabilityRequirement(
                CustomerLookupCapability::class,
                CustomerLookupPort::class,
                CustomerLookupAdapter::class,
            ),
            new CapabilityRequirement(
                PaymentAuthorizationCapability::class,
                PaymentAuthorizationPort::class,
                PaymentAuthorizationAdapter::class,
            ),
            new CapabilityRequirement(
                ProductCatalogCapability::class,
                ProductCatalogPort::class,
                ProductCatalogAdapter::class,
            ),
            new CapabilityRequirement(
                StockAvailabilityCapability::class,
                StockAvailabilityPort::class,
                StockAvailabilityAdapter::class,
            ),
        ];
    }
}

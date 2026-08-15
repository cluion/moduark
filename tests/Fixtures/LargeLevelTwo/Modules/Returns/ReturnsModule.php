<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Returns;

use Cluion\Moduark\CapabilityRequirement;
use Cluion\Moduark\Module;
use Tests\Fixtures\LargeLevelTwo\Capabilities\CustomerLookup as CustomerLookupCapability;
use Tests\Fixtures\LargeLevelTwo\Capabilities\NotificationDelivery as NotificationDeliveryCapability;
use Tests\Fixtures\LargeLevelTwo\Capabilities\PaymentAuthorization as PaymentAuthorizationCapability;
use Tests\Fixtures\LargeLevelTwo\Capabilities\ProductCatalog as ProductCatalogCapability;
use Tests\Fixtures\LargeLevelTwo\Capabilities\StockAvailability as StockAvailabilityCapability;
use Tests\Fixtures\LargeLevelTwo\Modules\Catalog\CatalogModule;
use Tests\Fixtures\LargeLevelTwo\Modules\Customer\CustomerModule;
use Tests\Fixtures\LargeLevelTwo\Modules\Inventory\InventoryModule;
use Tests\Fixtures\LargeLevelTwo\Modules\Notification\NotificationModule;
use Tests\Fixtures\LargeLevelTwo\Modules\Payment\PaymentModule;
use Tests\Fixtures\LargeLevelTwo\Modules\Returns\Adapters\Catalog\ProductCatalogAdapter;
use Tests\Fixtures\LargeLevelTwo\Modules\Returns\Adapters\Customer\CustomerLookupAdapter;
use Tests\Fixtures\LargeLevelTwo\Modules\Returns\Adapters\Inventory\StockAvailabilityAdapter;
use Tests\Fixtures\LargeLevelTwo\Modules\Returns\Adapters\Notification\NotificationDeliveryAdapter;
use Tests\Fixtures\LargeLevelTwo\Modules\Returns\Adapters\Payment\PaymentAuthorizationAdapter;
use Tests\Fixtures\LargeLevelTwo\Modules\Returns\Ports\CustomerLookup as CustomerLookupPort;
use Tests\Fixtures\LargeLevelTwo\Modules\Returns\Ports\NotificationDelivery as NotificationDeliveryPort;
use Tests\Fixtures\LargeLevelTwo\Modules\Returns\Ports\PaymentAuthorization as PaymentAuthorizationPort;
use Tests\Fixtures\LargeLevelTwo\Modules\Returns\Ports\ProductCatalog as ProductCatalogPort;
use Tests\Fixtures\LargeLevelTwo\Modules\Returns\Ports\StockAvailability as StockAvailabilityPort;

final class ReturnsModule extends Module
{
    /** @return list<class-string<Module>> */
    public function dependencies(): array
    {
        return [
            CatalogModule::class,
            CustomerModule::class,
            InventoryModule::class,
            NotificationModule::class,
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
                NotificationDeliveryCapability::class,
                NotificationDeliveryPort::class,
                NotificationDeliveryAdapter::class,
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

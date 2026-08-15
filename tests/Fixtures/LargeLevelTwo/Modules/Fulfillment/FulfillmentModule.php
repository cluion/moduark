<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Fulfillment;

use Cluion\Moduark\CapabilityRequirement;
use Cluion\Moduark\Module;
use Tests\Fixtures\LargeLevelTwo\Capabilities\CustomerLookup as CustomerLookupCapability;
use Tests\Fixtures\LargeLevelTwo\Capabilities\NotificationDelivery as NotificationDeliveryCapability;
use Tests\Fixtures\LargeLevelTwo\Capabilities\StockAvailability as StockAvailabilityCapability;
use Tests\Fixtures\LargeLevelTwo\Modules\Customer\CustomerModule;
use Tests\Fixtures\LargeLevelTwo\Modules\Fulfillment\Adapters\Customer\CustomerLookupAdapter;
use Tests\Fixtures\LargeLevelTwo\Modules\Fulfillment\Adapters\Inventory\StockAvailabilityAdapter;
use Tests\Fixtures\LargeLevelTwo\Modules\Fulfillment\Adapters\Notification\NotificationDeliveryAdapter;
use Tests\Fixtures\LargeLevelTwo\Modules\Fulfillment\Ports\CustomerLookup as CustomerLookupPort;
use Tests\Fixtures\LargeLevelTwo\Modules\Fulfillment\Ports\NotificationDelivery as NotificationDeliveryPort;
use Tests\Fixtures\LargeLevelTwo\Modules\Fulfillment\Ports\StockAvailability as StockAvailabilityPort;
use Tests\Fixtures\LargeLevelTwo\Modules\Inventory\InventoryModule;
use Tests\Fixtures\LargeLevelTwo\Modules\Notification\NotificationModule;

final class FulfillmentModule extends Module
{
    /** @return list<class-string<Module>> */
    public function dependencies(): array
    {
        return [
            CustomerModule::class,
            InventoryModule::class,
            NotificationModule::class,
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
                StockAvailabilityCapability::class,
                StockAvailabilityPort::class,
                StockAvailabilityAdapter::class,
            ),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Fixtures\DiverseLevelThree\Modules\Order;

use Cluion\Moduark\CapabilityRequirement;
use Cluion\Moduark\Module;
use Tests\Fixtures\DiverseLevelThree\Capabilities\CustomerLookup as CustomerLookupCapability;
use Tests\Fixtures\DiverseLevelThree\Capabilities\PaymentAuthorization as PaymentAuthorizationCapability;
use Tests\Fixtures\DiverseLevelThree\Modules\Customer\CustomerModule;
use Tests\Fixtures\DiverseLevelThree\Modules\Order\Adapters\Customer\CustomerLookupAdapter;
use Tests\Fixtures\DiverseLevelThree\Modules\Order\Adapters\Payment\PaymentAuthorizationAdapter;
use Tests\Fixtures\DiverseLevelThree\Modules\Order\Ports\CustomerLookup as CustomerLookupPort;
use Tests\Fixtures\DiverseLevelThree\Modules\Order\Ports\PaymentAuthorization as PaymentAuthorizationPort;
use Tests\Fixtures\DiverseLevelThree\Modules\Payment\PaymentModule;

final class OrderModule extends Module
{
    /** @return list<class-string<Module>> */
    public function dependencies(): array
    {
        return [CustomerModule::class, PaymentModule::class];
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
        ];
    }

    public function tables(): array
    {
        return ['orders', 'order_items'];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Fixtures\Lifecycle\Activation\Modules\Orders;

use Cluion\Moduark\CapabilityRequirement;
use Cluion\Moduark\Module;
use Tests\Fixtures\Lifecycle\Activation\Capabilities\Payments as PaymentsCapability;
use Tests\Fixtures\Lifecycle\Activation\Modules\Foundation\FoundationModule;
use Tests\Fixtures\Lifecycle\Activation\Modules\Orders\Adapters\PaymentsAdapter;
use Tests\Fixtures\Lifecycle\Activation\Modules\Orders\Ports\Payments as PaymentsPort;

final class OrdersModule extends Module
{
    /** @return list<class-string<Module>> */
    public function dependencies(): array
    {
        return [FoundationModule::class];
    }

    /** @return list<CapabilityRequirement> */
    public function requires(): array
    {
        return [new CapabilityRequirement(
            PaymentsCapability::class,
            PaymentsPort::class,
            PaymentsAdapter::class,
        )];
    }
}

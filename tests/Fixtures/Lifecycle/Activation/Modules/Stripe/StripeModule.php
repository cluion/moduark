<?php

declare(strict_types=1);

namespace Tests\Fixtures\Lifecycle\Activation\Modules\Stripe;

use Cluion\Moduark\Capability;
use Cluion\Moduark\Module;
use Tests\Fixtures\Lifecycle\Activation\Capabilities\Payments;

final class StripeModule extends Module
{
    /** @return list<class-string<Capability>> */
    public function provides(): array
    {
        return [Payments::class];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Fixtures\DiverseLevelThree\Modules\Payment;

use Cluion\Moduark\Capability;
use Cluion\Moduark\Module;
use Tests\Fixtures\DiverseLevelThree\Capabilities\PaymentAuthorization;
use Tests\Fixtures\DiverseLevelThree\Modules\Payment\Contracts\PaymentGateway;

final class PaymentModule extends Module
{
    /** @return list<class-string<Capability>> */
    public function provides(): array
    {
        return [PaymentAuthorization::class];
    }

    public function tables(): array
    {
        return ['payment_attempts'];
    }

    public function exports(): array
    {
        return [PaymentGateway::class];
    }
}

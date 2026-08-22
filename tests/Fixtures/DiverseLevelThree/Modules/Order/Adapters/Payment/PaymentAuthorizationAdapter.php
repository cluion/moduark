<?php

declare(strict_types=1);

namespace Tests\Fixtures\DiverseLevelThree\Modules\Order\Adapters\Payment;

use Tests\Fixtures\DiverseLevelThree\Modules\Order\Ports\PaymentAuthorization;
use Tests\Fixtures\DiverseLevelThree\Modules\Payment\Contracts\PaymentGateway;

final readonly class PaymentAuthorizationAdapter implements PaymentAuthorization
{
    public function __construct(private PaymentGateway $payments)
    {
    }

    public function authorized(int $customerId, int $amount): bool
    {
        return $this->payments->authorize($customerId, $amount);
    }
}

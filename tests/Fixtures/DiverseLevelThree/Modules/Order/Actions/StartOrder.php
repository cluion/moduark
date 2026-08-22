<?php

declare(strict_types=1);

namespace Tests\Fixtures\DiverseLevelThree\Modules\Order\Actions;

use Tests\Fixtures\DiverseLevelThree\Modules\Order\Ports\CustomerLookup;
use Tests\Fixtures\DiverseLevelThree\Modules\Order\Ports\PaymentAuthorization;

final readonly class StartOrder
{
    public function __construct(
        private CustomerLookup $customers,
        private PaymentAuthorization $payments,
    ) {
    }

    public function for(int $customerId, int $amount): string
    {
        return implode(' | ', [
            $this->customers->customerName($customerId),
            $this->payments->authorized($customerId, $amount) ? 'authorized' : 'declined',
        ]);
    }
}

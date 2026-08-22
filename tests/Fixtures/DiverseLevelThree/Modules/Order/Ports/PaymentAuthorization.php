<?php

declare(strict_types=1);

namespace Tests\Fixtures\DiverseLevelThree\Modules\Order\Ports;

interface PaymentAuthorization
{
    public function authorized(int $customerId, int $amount): bool;
}

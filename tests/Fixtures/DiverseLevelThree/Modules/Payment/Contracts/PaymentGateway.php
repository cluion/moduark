<?php

declare(strict_types=1);

namespace Tests\Fixtures\DiverseLevelThree\Modules\Payment\Contracts;

final class PaymentGateway
{
    public function authorize(int $customerId, int $amount): bool
    {
        return $customerId > 0 && $amount > 0;
    }
}

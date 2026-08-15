<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Payment\Contracts;

final class PaymentGateway
{
    public function authorize(int $customerId, string $sku): bool
    {
        return $customerId > 0 && $sku !== '';
    }
}

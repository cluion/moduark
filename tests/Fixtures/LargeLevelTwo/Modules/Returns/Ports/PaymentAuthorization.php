<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Returns\Ports;

interface PaymentAuthorization
{
    public function authorized(int $customerId, string $sku): bool;
}

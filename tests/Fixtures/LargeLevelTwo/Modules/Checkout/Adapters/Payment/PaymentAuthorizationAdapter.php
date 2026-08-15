<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Checkout\Adapters\Payment;

use Tests\Fixtures\LargeLevelTwo\Modules\Checkout\Ports\PaymentAuthorization;
use Tests\Fixtures\LargeLevelTwo\Modules\Payment\Contracts\PaymentGateway;

final readonly class PaymentAuthorizationAdapter implements PaymentAuthorization
{
    public function __construct(private PaymentGateway $service)
    {
    }

    public function authorized(int $customerId, string $sku): bool
    {
        return $this->service->authorize($customerId, $sku);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Fixtures\Lifecycle\Activation\Modules\Orders\Adapters;

use Tests\Fixtures\Lifecycle\Activation\Modules\Orders\Ports\Payments;

final class PaymentsAdapter implements Payments
{
    public function charge(int $amount): void
    {
    }
}

<?php

declare(strict_types=1);

namespace Tests\Fixtures\Lifecycle\Activation\Modules\Orders\Ports;

interface Payments
{
    public function charge(int $amount): void;
}

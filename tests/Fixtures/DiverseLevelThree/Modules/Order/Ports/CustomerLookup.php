<?php

declare(strict_types=1);

namespace Tests\Fixtures\DiverseLevelThree\Modules\Order\Ports;

interface CustomerLookup
{
    public function customerName(int $customerId): string;
}

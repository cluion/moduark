<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Checkout\Ports;

interface CustomerLookup
{
    public function customerName(int $customerId): string;
}

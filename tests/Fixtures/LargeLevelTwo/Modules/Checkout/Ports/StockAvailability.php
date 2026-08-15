<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Checkout\Ports;

interface StockAvailability
{
    public function inStock(string $sku): bool;
}

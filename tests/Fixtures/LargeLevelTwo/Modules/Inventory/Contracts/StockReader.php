<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Inventory\Contracts;

final class StockReader
{
    public function available(string $sku): bool
    {
        return $sku !== '';
    }
}

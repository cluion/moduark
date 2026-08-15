<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Returns\Adapters\Inventory;

use Tests\Fixtures\LargeLevelTwo\Modules\Returns\Ports\StockAvailability;
use Tests\Fixtures\LargeLevelTwo\Modules\Inventory\Contracts\StockReader;

final readonly class StockAvailabilityAdapter implements StockAvailability
{
    public function __construct(private StockReader $service)
    {
    }

    public function inStock(string $sku): bool
    {
        return $this->service->available($sku);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Inventory;

use Cluion\Moduark\Capability;
use Cluion\Moduark\Module;
use Tests\Fixtures\LargeLevelTwo\Capabilities\StockAvailability;

final class InventoryModule extends Module
{
    /** @return list<class-string<Capability>> */
    public function provides(): array
    {
        return [StockAvailability::class];
    }
}

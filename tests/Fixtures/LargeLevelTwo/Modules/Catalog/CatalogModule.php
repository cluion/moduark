<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Catalog;

use Cluion\Moduark\Capability;
use Cluion\Moduark\Module;
use Tests\Fixtures\LargeLevelTwo\Capabilities\ProductCatalog;

final class CatalogModule extends Module
{
    /** @return list<class-string<Capability>> */
    public function provides(): array
    {
        return [ProductCatalog::class];
    }
}

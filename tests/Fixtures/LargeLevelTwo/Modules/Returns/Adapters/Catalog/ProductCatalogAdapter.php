<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Returns\Adapters\Catalog;

use Tests\Fixtures\LargeLevelTwo\Modules\Returns\Ports\ProductCatalog;
use Tests\Fixtures\LargeLevelTwo\Modules\Catalog\Contracts\ProductCatalogReader;

final readonly class ProductCatalogAdapter implements ProductCatalog
{
    public function __construct(private ProductCatalogReader $service)
    {
    }

    public function productLabel(string $sku): string
    {
        return $this->service->label($sku);
    }
}

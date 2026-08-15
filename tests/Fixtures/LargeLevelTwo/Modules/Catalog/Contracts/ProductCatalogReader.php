<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Catalog\Contracts;

final class ProductCatalogReader
{
    public function label(string $sku): string
    {
        return 'Product '.$sku;
    }
}

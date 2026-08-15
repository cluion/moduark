<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Checkout\Ports;

interface ProductCatalog
{
    public function productLabel(string $sku): string;
}

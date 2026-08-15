<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Returns\Ports;

interface ProductCatalog
{
    public function productLabel(string $sku): string;
}

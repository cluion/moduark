<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Boundary;

use Cluion\Moduark\Analysis\Source\SourceSymbol;
use Cluion\Moduark\Discovery\DiscoveredModule;

interface PublicApi
{
    public function includes(SourceSymbol $symbol, DiscoveredModule $owner): bool;
}

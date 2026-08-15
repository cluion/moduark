<?php

declare(strict_types=1);

namespace Cluion\Moduark\Graph;

enum CapabilityGraphEdgeType: string
{
    case Provides = 'provides';
    case Requires = 'requires';
}

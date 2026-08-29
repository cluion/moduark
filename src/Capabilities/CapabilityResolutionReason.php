<?php

declare(strict_types=1);

namespace Cluion\Moduark\Capabilities;

enum CapabilityResolutionReason: string
{
    case MissingProvider = 'missing-provider';
    case AmbiguousProvider = 'ambiguous-provider';
    case DuplicatePort = 'duplicate-port';
}

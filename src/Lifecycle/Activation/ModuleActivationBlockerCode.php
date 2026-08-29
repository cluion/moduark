<?php

declare(strict_types=1);

namespace Cluion\Moduark\Lifecycle\Activation;

enum ModuleActivationBlockerCode: string
{
    case InvalidMetadata = 'invalid-metadata';
    case MissingDependency = 'missing-dependency';
    case CircularDependency = 'circular-dependency';
    case MissingCapabilityProvider = 'missing-capability-provider';
    case AmbiguousCapabilityProvider = 'ambiguous-capability-provider';
    case DuplicateCapabilityPort = 'duplicate-capability-port';
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Lifecycle\Activation;

enum ModuleActivationDriver: string
{
    case Standalone = 'standalone';
    case Nwidart = 'nwidart';
}

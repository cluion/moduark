<?php

declare(strict_types=1);

namespace Cluion\Moduark\Lifecycle\Activation;

enum ModuleActivationIntent: string
{
    case Enable = 'enable';
    case Disable = 'disable';
}

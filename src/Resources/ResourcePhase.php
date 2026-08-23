<?php

declare(strict_types=1);

namespace Cluion\Moduark\Resources;

enum ResourcePhase: string
{
    case Register = 'register';
    case Boot = 'boot';
}

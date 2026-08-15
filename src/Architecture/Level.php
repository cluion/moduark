<?php

declare(strict_types=1);

namespace Cluion\Moduark\Architecture;

enum Level: int
{
    case Organization = 0;
    case Modular = 1;
    case Decoupled = 2;
    case Isolated = 3;

    public function label(): string
    {
        return $this->name;
    }
}

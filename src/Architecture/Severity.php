<?php

declare(strict_types=1);

namespace Cluion\Moduark\Architecture;

enum Severity: string
{
    case Warning = 'warning';
    case Error = 'error';

    public function blocks(): bool
    {
        return $this === self::Error;
    }
}

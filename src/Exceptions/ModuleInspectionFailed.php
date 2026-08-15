<?php

declare(strict_types=1);

namespace Cluion\Moduark\Exceptions;

use RuntimeException;

final class ModuleInspectionFailed extends RuntimeException
{
    public static function unknownModule(string $module): self
    {
        return new self("Module [{$module}] was not found.");
    }
}

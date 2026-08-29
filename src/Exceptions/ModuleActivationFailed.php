<?php

declare(strict_types=1);

namespace Cluion\Moduark\Exceptions;

use InvalidArgumentException;

final class ModuleActivationFailed extends InvalidArgumentException
{
    public static function unknownModule(string $module): self
    {
        return new self("Unknown Module [{$module}].");
    }

    public static function installedPackage(string $module): self
    {
        return new self(
            "Package Module [{$module}] is always active while installed; use Composer to install or remove it.",
        );
    }
}

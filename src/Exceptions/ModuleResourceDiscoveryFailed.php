<?php

declare(strict_types=1);

namespace Cluion\Moduark\Exceptions;

use RuntimeException;

final class ModuleResourceDiscoveryFailed extends RuntimeException
{
    public static function commandScanFailed(string $moduleClass, string $path): self
    {
        return new self("Unable to scan commands for Module [{$moduleClass}] at [{$path}].");
    }

    public static function invalidCommand(string $moduleClass, string $commandClass, string $path): self
    {
        return new self(
            "Module [{$moduleClass}] command [{$commandClass}] at [{$path}] must be an autoloadable, instantiable Laravel command.",
        );
    }

    public static function commandSourceMismatch(
        string $moduleClass,
        string $commandClass,
        string $expectedPath,
        string $actualPath,
    ): self {
        return new self(
            "Module [{$moduleClass}] command [{$commandClass}] expected source [{$expectedPath}], but Composer loaded [{$actualPath}].",
        );
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Exceptions;

use RuntimeException;

final class CapabilityGraphFailed extends RuntimeException
{
    public static function duplicateModule(string $moduleClass): self
    {
        return new self("Capability graph Module node [{$moduleClass}] was provided more than once.");
    }

    public static function undiscoveredModule(string $moduleClass): self
    {
        return new self("Capability graph Module node [{$moduleClass}] must be discovered.");
    }

    public static function duplicateCapability(string $capability): self
    {
        return new self("Capability graph node [{$capability}] was provided more than once.");
    }

    public static function missingModuleEndpoint(string $moduleClass): self
    {
        return new self("Capability graph edge Module endpoint [{$moduleClass}] has no node.");
    }

    public static function missingCapabilityEndpoint(string $capability): self
    {
        return new self("Capability graph edge Capability endpoint [{$capability}] has no node.");
    }

    public static function duplicateEdge(
        string $type,
        string $moduleClass,
        string $capability,
    ): self {
        return new self(
            "Capability graph [{$type}] edge [{$moduleClass}] -> [{$capability}] was provided more than once.",
        );
    }

    public static function unknownModule(string $module): self
    {
        return new self("Module [{$module}] was not found in the Capability graph.");
    }
}

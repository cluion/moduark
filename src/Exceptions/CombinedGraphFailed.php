<?php

declare(strict_types=1);

namespace Cluion\Moduark\Exceptions;

use RuntimeException;

final class CombinedGraphFailed extends RuntimeException
{
    public static function missingModule(string $moduleClass): self
    {
        return new self(
            "Combined graph Capability Module endpoint [{$moduleClass}] has no Module graph node.",
        );
    }

    public static function invalidModule(string $moduleClass): self
    {
        return new self(
            "Combined graph Capability Module endpoint [{$moduleClass}] must be discovered.",
        );
    }

    public static function missingCapabilityModule(string $moduleClass): self
    {
        return new self(
            "Combined graph Module [{$moduleClass}] is missing from the Capability graph.",
        );
    }

    public static function mismatchedModule(string $moduleClass): self
    {
        return new self(
            "Combined graph Module node [{$moduleClass}] differs between graph views.",
        );
    }

    public static function unknownModule(string $module): self
    {
        return new self("Module [{$module}] was not found in the combined graph.");
    }
}

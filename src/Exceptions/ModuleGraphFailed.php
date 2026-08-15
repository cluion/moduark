<?php

declare(strict_types=1);

namespace Cluion\Moduark\Exceptions;

use RuntimeException;

final class ModuleGraphFailed extends RuntimeException
{
    public static function duplicateNode(string $moduleClass): self
    {
        return new self("Module graph node [{$moduleClass}] was provided more than once.");
    }

    public static function missingEndpoint(string $moduleClass): self
    {
        return new self("Module graph edge endpoint [{$moduleClass}] has no node.");
    }

    public static function invalidSource(string $moduleClass): self
    {
        return new self("Module graph edge source [{$moduleClass}] must be a discovered Module.");
    }

    public static function duplicateEdge(string $source, string $target): self
    {
        return new self("Module graph edge [{$source}] -> [{$target}] was provided more than once.");
    }

    public static function unknownModule(string $module): self
    {
        return new self("Module [{$module}] was not found in the dependency graph.");
    }
}

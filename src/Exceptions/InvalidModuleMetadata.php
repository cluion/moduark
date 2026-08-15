<?php

declare(strict_types=1);

namespace Cluion\Moduark\Exceptions;

use RuntimeException;

final class InvalidModuleMetadata extends RuntimeException
{
    public static function invalidModuleClass(string $moduleClass): self
    {
        return new self("Module entry class [{$moduleClass}] must extend Cluion\\Moduark\\Module.");
    }

    public static function invalidClassReference(
        string $moduleClass,
        string $method,
        mixed $value,
        string $expectedType,
    ): self {
        return new self(sprintf(
            '%s::%s() must return %s entries; received %s.',
            $moduleClass,
            $method,
            $expectedType,
            get_debug_type($value),
        ));
    }

    public static function duplicateReference(string $moduleClass, string $method, string $reference): self
    {
        return new self("{$moduleClass}::{$method}() contains duplicate reference [{$reference}].");
    }

    public static function duplicateModule(string $moduleClass): self
    {
        return new self("Module entry class [{$moduleClass}] was provided more than once.");
    }

    public static function missingDependency(string $moduleClass, string $dependency): self
    {
        return new self("Module [{$moduleClass}] depends on missing module [{$dependency}].");
    }
}

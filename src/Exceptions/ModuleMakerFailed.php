<?php

declare(strict_types=1);

namespace Cluion\Moduark\Exceptions;

use RuntimeException;

final class ModuleMakerFailed extends RuntimeException
{
    public static function unknownModule(string $module): self
    {
        return new self("Module [{$module}] was not found.");
    }

    public static function unsupportedType(string $type): self
    {
        return new self("Maker type [{$type}] is not supported; expected model or controller.");
    }

    public static function invalidName(string $name): self
    {
        return new self(
            "Maker name [{$name}] must contain one or more StudlyCase class segments.",
        );
    }

    public static function reservedName(string $name): self
    {
        return new self("Maker class name [{$name}] is reserved by PHP.");
    }

    public static function applicationPathUnavailable(): self
    {
        return new self('The Laravel application source path is unavailable.');
    }

    public static function applicationNamespaceUnavailable(): self
    {
        return new self('The Laravel application namespace could not be resolved.');
    }

    public static function externalModulePath(string $module, string $path, string $applicationPath): self
    {
        return new self(
            "Module [{$module}] path [{$path}] must be inside Laravel application path [{$applicationPath}] for module:make.",
        );
    }

    public static function namespaceMismatch(string $module, string $namespace, string $expected): self
    {
        return new self(
            "Module [{$module}] namespace [{$namespace}] must match application path namespace [{$expected}] for module:make.",
        );
    }

    public static function unsupportedOption(string $option, string $type): self
    {
        return new self("The --{$option} option is not supported for Maker type [{$type}].");
    }

    /** @param list<string> $options */
    public static function conflictingOptions(array $options): self
    {
        return new self(sprintf(
            'The controller Maker options [%s] cannot be combined.',
            implode(', ', array_map(static fn (string $option): string => '--'.$option, $options)),
        ));
    }
}

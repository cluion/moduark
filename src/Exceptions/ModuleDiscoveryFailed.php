<?php

declare(strict_types=1);

namespace Cluion\Moduark\Exceptions;

use RuntimeException;

final class ModuleDiscoveryFailed extends RuntimeException
{
    public static function scanFailed(string $path): self
    {
        return new self("Unable to scan Module path [{$path}].");
    }

    public static function unreadableFile(string $path): self
    {
        return new self("Unable to read Module entry file [{$path}].");
    }

    public static function invalidSyntax(string $path, string $message): self
    {
        return new self("Invalid PHP syntax in Module entry file [{$path}]: {$message}");
    }

    public static function invalidFileName(string $path, string $expected): self
    {
        return new self("Module entry file [{$path}] must be named [{$expected}].");
    }

    public static function missingClass(string $path): self
    {
        return new self("Module entry file [{$path}] must declare a named class.");
    }

    public static function invalidClassName(string $path, string $expected, string $actual): self
    {
        return new self("Module entry file [{$path}] must declare class [{$expected}]; found [{$actual}].");
    }

    public static function invalidNamespace(string $path, string $expected, string $actual): self
    {
        return new self("Module entry file [{$path}] namespace must end with [{$expected}]; found [{$actual}].");
    }

    public static function classNotAutoloadable(string $moduleClass, string $path): self
    {
        return new self("Module entry class [{$moduleClass}] declared in [{$path}] is not autoloadable.");
    }

    public static function invalidModuleClass(string $moduleClass, string $path): self
    {
        return new self("Module entry class [{$moduleClass}] declared in [{$path}] must be a concrete Cluion\\Moduark\\Module.");
    }

    public static function sourceMismatch(
        string $moduleClass,
        string $expectedPath,
        string $autoloadedPath,
    ): self {
        return new self("Module entry class [{$moduleClass}] from [{$expectedPath}] autoloaded from [{$autoloadedPath}].");
    }

    public static function duplicateName(string $name, string $firstPath, string $secondPath): self
    {
        return new self("Duplicate Module name [{$name}] in [{$firstPath}] and [{$secondPath}].");
    }

    public static function duplicateClass(string $moduleClass, string $firstPath, string $secondPath): self
    {
        return new self("Duplicate Module entry class [{$moduleClass}] in [{$firstPath}] and [{$secondPath}].");
    }
}

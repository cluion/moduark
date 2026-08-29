<?php

declare(strict_types=1);

namespace Cluion\Moduark\Exceptions;

use RuntimeException;

final class PackageModuleDiscoveryFailed extends RuntimeException
{
    public static function unreadableManifest(string $path): self
    {
        return new self("Unable to read Composer package manifest [{$path}].");
    }

    public static function runtimeManifestUnavailable(): self
    {
        return new self('Unable to locate the Composer runtime package manifest.');
    }

    public static function invalidManifest(string $path): self
    {
        return new self("Composer package manifest [{$path}] is invalid.");
    }

    public static function invalidMetadata(string $package): self
    {
        return new self("Composer package [{$package}] has invalid Moduark metadata.");
    }

    public static function invalidInstallPath(string $package, string $path): self
    {
        return new self("Composer package [{$package}] install path [{$path}] is invalid.");
    }

    public static function invalidDescriptor(string $package, string $message): self
    {
        return new self("Composer package [{$package}] has an invalid Module descriptor: {$message}");
    }

    public static function duplicateName(
        string $name,
        string $firstPackage,
        string $secondPackage,
    ): self {
        return new self(
            "Duplicate package Module name [{$name}] in [{$firstPackage}] and [{$secondPackage}].",
        );
    }

    public static function duplicateClass(
        string $moduleClass,
        string $firstPackage,
        string $secondPackage,
    ): self {
        return new self(
            "Duplicate package Module class [{$moduleClass}] in [{$firstPackage}] and [{$secondPackage}].",
        );
    }
}

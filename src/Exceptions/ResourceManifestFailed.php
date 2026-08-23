<?php

declare(strict_types=1);

namespace Cluion\Moduark\Exceptions;

use RuntimeException;

final class ResourceManifestFailed extends RuntimeException
{
    public static function invalidData(string $context): self
    {
        return new self("Resource manifest data [{$context}] must contain only scalar, null, and nested array values.");
    }

    public static function invalidIdentity(string $kind, string $identity): self
    {
        return new self("Resource {$kind} [{$identity}] is invalid.");
    }

    public static function duplicatePlugin(string $plugin): self
    {
        return new self("Resource plugin [{$plugin}] is already registered.");
    }

    public static function unknownPlugin(string $moduleClass, string $plugin): self
    {
        return new self("Module [{$moduleClass}] configures unknown resource plugin [{$plugin}].");
    }

    public static function duplicateResource(string $moduleClass, string $plugin, string $identity): self
    {
        return new self("Module [{$moduleClass}] contains duplicate [{$plugin}] resource [{$identity}].");
    }

    public static function inactiveResource(string $moduleClass): self
    {
        return new self("Resource descriptor references Module [{$moduleClass}] outside the active Module set.");
    }

    public static function moduleSetMismatch(): self
    {
        return new self('The resource manifest Module set does not match the canonical Module registry.');
    }

    public static function pluginMismatch(string $expected, string $actual): self
    {
        return new self("Resource plugin [{$expected}] returned descriptor for plugin [{$actual}].");
    }

    public static function moduleMismatch(string $expected, string $actual): self
    {
        return new self("Resource discoverer for Module [{$expected}] returned descriptor for Module [{$actual}].");
    }

    public static function invalidPayload(): self
    {
        return new self('The resource manifest payload is invalid.');
    }

    public static function invalidConfiguration(string $moduleClass, string $plugin): self
    {
        return new self("Module [{$moduleClass}] resource plugin [{$plugin}] configuration is invalid.");
    }

    public static function missingResource(string $moduleClass, string $plugin, string $path): self
    {
        return new self("Module [{$moduleClass}] resource plugin [{$plugin}] references missing path [{$path}].");
    }

    public static function unsafePath(string $moduleClass, string $plugin, string $path): self
    {
        return new self("Module [{$moduleClass}] resource plugin [{$plugin}] path [{$path}] must stay inside the Module root.");
    }
}

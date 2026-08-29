<?php

declare(strict_types=1);

namespace Cluion\Moduark\Package;

use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Exceptions\PackageModuleDiscoveryFailed;
use Cluion\Moduark\Module;
use ReflectionClass;

final readonly class PackageModuleDescriptor
{
    /**
     * @param class-string<Module> $moduleClass
     */
    private function __construct(
        private string $package,
        private string $name,
        private string $moduleClass,
        private string $relativePath,
        private string $absolutePath,
        private string $namespace,
    ) {
    }

    /** @param array<mixed> $payload */
    public static function fromArray(string $package, string $installPath, array $payload): self
    {
        $name = $payload['name'] ?? null;
        $moduleClass = $payload['class'] ?? null;
        $relativePath = $payload['path'] ?? null;

        if (preg_match('/\A[a-z0-9](?:[a-z0-9._-]*[a-z0-9])?\/[a-z0-9](?:[a-z0-9._-]*[a-z0-9])?\z/', $package) !== 1
            || ! is_string($name)
            || preg_match('/\A[A-Z][A-Za-z0-9]*\z/', $name) !== 1
            || ! is_string($moduleClass)
            || $moduleClass === ''
            || ! is_string($relativePath)
            || ! self::portablePath($relativePath)) {
            throw PackageModuleDiscoveryFailed::invalidDescriptor($package, 'identity fields are invalid.');
        }

        $installRoot = realpath($installPath);

        if ($installRoot === false || ! is_dir($installRoot)) {
            throw PackageModuleDiscoveryFailed::invalidInstallPath($package, $installPath);
        }

        $absolutePath = realpath($installRoot.'/'.$relativePath);

        if ($absolutePath === false
            || ! is_file($absolutePath)
            || ! str_starts_with($absolutePath, rtrim($installRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            throw PackageModuleDiscoveryFailed::invalidDescriptor(
                $package,
                "source [{$relativePath}] is outside the installed package or is unreadable.",
            );
        }

        if (! class_exists($moduleClass)) {
            throw PackageModuleDiscoveryFailed::invalidDescriptor(
                $package,
                "class [{$moduleClass}] is not autoloadable.",
            );
        }

        if (! is_a($moduleClass, Module::class, true)) {
            throw PackageModuleDiscoveryFailed::invalidDescriptor(
                $package,
                "class [{$moduleClass}] is not a Moduark Module.",
            );
        }

        /** @var class-string<Module> $moduleClass */
        $reflection = new ReflectionClass($moduleClass);
        $autoloadedPath = $reflection->getFileName();
        $autoloadedPath = is_string($autoloadedPath) ? realpath($autoloadedPath) : false;

        if (! $reflection->isInstantiable()
            || $reflection->getShortName() !== $name.'Module'
            || $autoloadedPath !== $absolutePath) {
            throw PackageModuleDiscoveryFailed::invalidDescriptor(
                $package,
                "class [{$moduleClass}] does not match Module [{$name}] source [{$relativePath}].",
            );
        }

        $namespace = $reflection->getNamespaceName();

        if ($namespace === '') {
            throw PackageModuleDiscoveryFailed::invalidDescriptor(
                $package,
                "class [{$moduleClass}] must have a namespace.",
            );
        }

        return new self(
            $package,
            $name,
            $moduleClass,
            $relativePath,
            $absolutePath,
            $namespace,
        );
    }

    public function package(): string
    {
        return $this->package;
    }

    public function name(): string
    {
        return $this->name;
    }

    /** @return class-string<Module> */
    public function moduleClass(): string
    {
        return $this->moduleClass;
    }

    public function relativePath(): string
    {
        return $this->relativePath;
    }

    public function absolutePath(): string
    {
        return $this->absolutePath;
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    public function discoveredModule(): DiscoveredModule
    {
        return new DiscoveredModule(
            $this->name,
            $this->moduleClass,
            $this->absolutePath,
            $this->namespace,
        );
    }

    /**
     * @return array{
     *     package: string,
     *     name: string,
     *     class: class-string<Module>,
     *     path: string,
     *     namespace: string
     * }
     */
    public function toArray(): array
    {
        return [
            'package' => $this->package,
            'name' => $this->name,
            'class' => $this->moduleClass,
            'path' => $this->relativePath,
            'namespace' => $this->namespace,
        ];
    }

    private static function portablePath(string $path): bool
    {
        if ($path === ''
            || trim($path) !== $path
            || str_contains($path, '\\')
            || str_starts_with($path, '/')
            || preg_match('/\A[A-Za-z]:\//', $path) === 1
            || ! str_ends_with(strtolower($path), '.php')) {
            return false;
        }

        $segments = explode('/', $path);

        return ! in_array('', $segments, true)
            && ! in_array('.', $segments, true)
            && ! in_array('..', $segments, true);
    }
}

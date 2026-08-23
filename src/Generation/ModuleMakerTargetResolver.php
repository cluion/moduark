<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Exceptions\ModuleMakerFailed;
use Cluion\Moduark\Registry\ModuleRegistry;
use Illuminate\Contracts\Foundation\Application;
use ParseError;
use RuntimeException;

final readonly class ModuleMakerTargetResolver
{
    public function __construct(
        private Application $application,
        private ModuleRegistry $registry,
    ) {
    }

    public function resolve(
        string $module,
        GeneratorDescriptor $descriptor,
        string $name,
    ): ModuleMakerTarget
    {
        $className = $this->className($name);
        $discovered = $this->findModule($module);
        $applicationPath = $this->applicationPath();
        $modulePath = dirname($discovered->path());

        if (! $this->isWithin($modulePath, $applicationPath)) {
            throw ModuleMakerFailed::externalModulePath(
                $discovered->name(),
                $modulePath,
                $applicationPath,
            );
        }

        $relativePath = trim(substr(
            $this->canonicalPath($modulePath),
            strlen($this->canonicalPath($applicationPath)),
        ), '/');
        $applicationNamespace = $this->applicationNamespace();
        $expectedNamespace = $applicationNamespace;

        if ($relativePath !== '') {
            $expectedNamespace .= '\\'.str_replace('/', '\\', $relativePath);
        }

        if ($discovered->namespace() !== $expectedNamespace) {
            throw ModuleMakerFailed::namespaceMismatch(
                $discovered->name(),
                $discovered->namespace(),
                $expectedNamespace,
            );
        }

        return new ModuleMakerTarget(
            $discovered->namespace().'\\'.$descriptor->targetNamespace().'\\'.$className,
            $modulePath.'/'.str_replace('\\', '/', $descriptor->targetNamespace().'\\'.$className).'.php',
            str_replace('\\', '/', $descriptor->targetNamespace().'\\'.$className).'.php',
        );
    }

    private function findModule(string $module): DiscoveredModule
    {
        foreach ($this->registry->all() as $candidate) {
            if (strcasecmp($candidate->name(), $module) === 0) {
                return $candidate;
            }
        }

        throw ModuleMakerFailed::unknownModule($module);
    }

    private function className(string $name): string
    {
        $name = str_replace('\\', '/', $name);

        if (preg_match('/\A[A-Z][A-Za-z0-9]*(?:\/[A-Z][A-Za-z0-9]*)*\z/D', $name) !== 1) {
            throw ModuleMakerFailed::invalidName($name);
        }

        $segments = explode('/', $name);
        $shortName = end($segments);

        try {
            $tokens = token_get_all("<?php class {$shortName} {}", TOKEN_PARSE);

            if ($tokens === []) {
                throw ModuleMakerFailed::reservedName($shortName);
            }
        } catch (ParseError) {
            throw ModuleMakerFailed::reservedName($shortName);
        }

        return str_replace('/', '\\', $name);
    }

    private function applicationPath(): string
    {
        $path = $this->application->make('path');

        if (! is_string($path) || $path === '') {
            throw ModuleMakerFailed::applicationPathUnavailable();
        }

        return $path;
    }

    private function applicationNamespace(): string
    {
        try {
            $namespace = trim($this->application->getNamespace(), '\\');
        } catch (RuntimeException) {
            throw ModuleMakerFailed::applicationNamespaceUnavailable();
        }

        if ($namespace === '') {
            throw ModuleMakerFailed::applicationNamespaceUnavailable();
        }

        return $namespace;
    }

    private function canonicalPath(string $path): string
    {
        $resolved = realpath($path);

        return $this->normalize($resolved === false ? $path : $resolved);
    }

    private function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        return strlen($path) > 1 ? rtrim($path, '/') : $path;
    }

    private function isWithin(string $path, string $basePath): bool
    {
        $path = $this->canonicalPath($path);
        $basePath = $this->canonicalPath($basePath);

        if (DIRECTORY_SEPARATOR === '\\') {
            $path = strtolower($path);
            $basePath = strtolower($basePath);
        }

        return $path === $basePath || str_starts_with($path, $basePath.'/');
    }
}

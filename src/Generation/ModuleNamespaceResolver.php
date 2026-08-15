<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use Cluion\Moduark\Exceptions\ModuleGenerationFailed;
use Composer\Autoload\ClassLoader;

final class ModuleNamespaceResolver
{
    public function resolve(string $rootPath): string
    {
        $configuredPath = $rootPath;
        $rootPath = $this->canonicalPath($rootPath);
        $candidates = [];

        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            foreach ($loader->getPrefixesPsr4() as $prefix => $paths) {
                foreach ($paths as $path) {
                    $basePath = $this->canonicalPath($path);

                    if (! $this->isWithin($rootPath, $basePath)) {
                        continue;
                    }

                    $relativePath = trim(substr($rootPath, strlen($basePath)), '/');
                    $namespace = trim($prefix, '\\');

                    if ($relativePath !== '') {
                        $namespace .= '\\'.str_replace('/', '\\', $relativePath);
                    }

                    $candidates[] = [
                        'base_length' => strlen($basePath),
                        'namespace' => $namespace,
                    ];
                }
            }
        }

        if ($candidates === []) {
            throw ModuleGenerationFailed::namespaceNotResolvable($configuredPath);
        }

        usort($candidates, static function (array $left, array $right): int {
            return $right['base_length'] <=> $left['base_length']
                ?: strcmp($left['namespace'], $right['namespace']);
        });

        $bestLength = $candidates[0]['base_length'];
        $namespaces = [];

        foreach ($candidates as $candidate) {
            if ($candidate['base_length'] !== $bestLength) {
                break;
            }

            $namespaces[$candidate['namespace']] = $candidate['namespace'];
        }

        $namespaces = array_values($namespaces);

        if (count($namespaces) !== 1) {
            throw ModuleGenerationFailed::ambiguousNamespace($configuredPath, $namespaces);
        }

        return $namespaces[0];
    }

    private function canonicalPath(string $path): string
    {
        $resolved = realpath($path);

        if ($resolved !== false) {
            return $this->normalize($resolved);
        }

        $segments = [];
        $current = $path;

        while (! file_exists($current)) {
            $parent = dirname($current);
            $segment = basename($current);

            if ($parent === $current || $segment === '.' || $segment === '..') {
                throw ModuleGenerationFailed::namespaceNotResolvable($path);
            }

            array_unshift($segments, $segment);
            $current = $parent;
        }

        $resolved = realpath($current);

        if ($resolved === false) {
            throw ModuleGenerationFailed::namespaceNotResolvable($path);
        }

        return $this->normalize($resolved.'/'.implode('/', $segments));
    }

    private function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        if (strlen($path) > 1) {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    private function isWithin(string $path, string $basePath): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $path = strtolower($path);
            $basePath = strtolower($basePath);
        }

        return $path === $basePath || str_starts_with($path, $basePath.'/');
    }
}

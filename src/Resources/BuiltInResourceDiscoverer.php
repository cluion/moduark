<?php

declare(strict_types=1);

namespace Cluion\Moduark\Resources;

use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Exceptions\ResourceManifestFailed;
use Cluion\Moduark\Metadata\ModuleDescriptor;

final readonly class BuiltInResourceDiscoverer implements ResourceDiscoverer
{
    public function __construct(
        private string $plugin,
        private CommandResourceScanner $commands,
    ) {
    }

    public function discover(
        DiscoveredModule $module,
        ModuleDescriptor $metadata,
        array $moduleConfiguration,
    ): array {
        return match ($this->plugin) {
            'routes' => $this->routes($module, $moduleConfiguration),
            'config' => $this->configFiles($module, $moduleConfiguration),
            'views' => $this->directory($module, 'views', 'resources/views', strtolower($module->name()), true),
            'translations' => $this->directory($module, 'translations', 'resources/lang', strtolower($module->name()), true),
            'migrations' => $this->migrationDirectory($module),
            'commands' => $this->commands($module, $moduleConfiguration),
            'factories' => $this->optInDirectories($module, $moduleConfiguration, 'factories', ['Database/Factories', 'database/factories', 'src/Database/Factories']),
            'seeders' => $this->classList($module, $moduleConfiguration, 'seeders'),
            'policies' => $this->classMap($module, $moduleConfiguration, 'policies'),
            'events' => $this->events($module, $moduleConfiguration),
            'listeners' => $this->listenerMap($module, $moduleConfiguration),
            'components' => $this->components($module, $moduleConfiguration),
            'providers' => $this->providers($module, $metadata),
            'assets' => $this->assets($module, $moduleConfiguration),
            'tests' => $this->testDirectories($module, $moduleConfiguration),
            'extensions' => $this->extensions($module, $moduleConfiguration),
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $moduleConfiguration
     * @return list<ResourceDescriptor>
     */
    private function routes(DiscoveredModule $module, array $moduleConfiguration): array
    {
        $resources = [];

        foreach (['web.php', 'api.php'] as $routeFile) {
            $path = $this->moduleRoot($module).'/routes/'.$routeFile;

            if (is_file($path)) {
                $resources[] = new ResourceDescriptor(
                    $module->moduleClass(),
                    'routes',
                    $routeFile,
                    $path,
                    attributes: ['conventional' => true],
                );
            }
        }

        $configured = $moduleConfiguration['routes'] ?? [];

        if (! is_array($configured) || ! array_is_list($configured)) {
            throw ResourceManifestFailed::invalidConfiguration($module->moduleClass(), 'routes');
        }

        foreach ($configured as $route) {
            if (is_string($route)) {
                $relativePath = $route;
                $group = [];
            } elseif (is_array($route)) {
                $relativePath = $route['path'] ?? null;
                $group = $route['group'] ?? [];
            } else {
                throw ResourceManifestFailed::invalidConfiguration($module->moduleClass(), 'routes');
            }

            if (! is_string($relativePath) || ! is_array($group)) {
                throw ResourceManifestFailed::invalidConfiguration($module->moduleClass(), 'routes');
            }

            $path = $this->requiredFile($module, 'routes', $relativePath);
            $resources[] = new ResourceDescriptor(
                $module->moduleClass(),
                'routes',
                $relativePath,
                $path,
                attributes: ['group' => $group],
            );
        }

        return $resources;
    }

    /**
     * @param array<string, mixed> $moduleConfiguration
     * @return list<ResourceDescriptor>
     */
    private function configFiles(DiscoveredModule $module, array $moduleConfiguration): array
    {
        $configured = $moduleConfiguration['config'] ?? [];

        if (! is_array($configured) || ! array_is_list($configured)) {
            throw ResourceManifestFailed::invalidConfiguration($module->moduleClass(), 'config');
        }

        $resources = [];

        foreach ($configured as $config) {
            if (! is_array($config)
                || ! is_string($config['path'] ?? null)
                || ! is_string($config['key'] ?? null)
                || trim($config['key']) === '') {
                throw ResourceManifestFailed::invalidConfiguration($module->moduleClass(), 'config');
            }

            $relativePath = $config['path'];
            $key = $config['key'];
            $publish = $config['publish'] ?? false;

            if (! is_bool($publish)) {
                throw ResourceManifestFailed::invalidConfiguration($module->moduleClass(), 'config');
            }

            $path = $this->requiredFile($module, 'config', $relativePath);
            $values = require $path;

            if (! is_array($values)) {
                throw ResourceManifestFailed::invalidConfiguration($module->moduleClass(), 'config');
            }

            $resources[] = new ResourceDescriptor(
                $module->moduleClass(),
                'config',
                $relativePath,
                $path,
                $key,
                [
                    'key' => $key,
                    'publish' => $publish,
                    'values' => ResourceData::normalizeMap(
                        $values,
                        $module->moduleClass().'.config.'.$key,
                    ),
                ],
                strtolower($key),
            );
        }

        return $resources;
    }

    /** @return list<ResourceDescriptor> */
    private function directory(
        DiscoveredModule $module,
        string $plugin,
        string $relativePath,
        ?string $namespace,
        bool $conventional = false,
    ): array {
        $path = $this->moduleRoot($module).'/'.$relativePath;

        return is_dir($path)
            ? [new ResourceDescriptor(
                $module->moduleClass(),
                $plugin,
                $relativePath,
                $path,
                $namespace,
                attributes: $conventional ? ['conventional' => true] : [],
                collisionKey: $namespace,
            )]
            : [];
    }

    /** @return list<ResourceDescriptor> */
    private function migrationDirectory(DiscoveredModule $module): array
    {
        foreach (['Database/Migrations', 'database/migrations'] as $relativePath) {
            $resources = $this->directory($module, 'migrations', $relativePath, null, true);

            if ($resources !== []) {
                return $resources;
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $moduleConfiguration
     * @return list<ResourceDescriptor>
     */
    private function commands(DiscoveredModule $module, array $moduleConfiguration): array
    {
        $configuration = $moduleConfiguration['commands'] ?? [];
        $recursive = is_array($configuration) && ($configuration['recursive'] ?? false) === true;
        $path = dirname($module->path()).'/Console/Commands';

        return array_map(
            static fn (array $command): ResourceDescriptor => new ResourceDescriptor(
                $module->moduleClass(),
                'commands',
                $command['class'],
                $command['path'],
                attributes: [
                    'class' => $command['class'],
                    'conventional' => ! $recursive,
                ],
                collisionKey: strtolower($command['class']),
            ),
            $this->commands->scan($module, $path, $recursive),
        );
    }

    /** @return list<ResourceDescriptor> */
    private function providers(DiscoveredModule $module, ModuleDescriptor $metadata): array
    {
        return array_map(
            static fn (string $provider): ResourceDescriptor => new ResourceDescriptor(
                $module->moduleClass(),
                'providers',
                $provider,
                attributes: ['class' => $provider],
            ),
            $metadata->providers(),
        );
    }

    /**
     * @param array<string, mixed> $moduleConfiguration
     * @param list<string> $conventions
     * @return list<ResourceDescriptor>
     */
    private function optInDirectories(
        DiscoveredModule $module,
        array $moduleConfiguration,
        string $plugin,
        array $conventions,
    ): array {
        if (! array_key_exists($plugin, $moduleConfiguration)) {
            return [];
        }

        $configured = $moduleConfiguration[$plugin];
        $paths = [];

        if ($configured === true) {
            $seenPaths = [];

            foreach ($conventions as $convention) {
                $path = $this->moduleRoot($module).'/'.$convention;
                $realPath = realpath($path);
                $pathKey = is_string($realPath) ? strtolower($realPath) : null;

                if ($realPath !== false && is_dir($realPath) && $pathKey !== null && ! isset($seenPaths[$pathKey])) {
                    $seenPaths[$pathKey] = true;
                    $paths[] = $convention;
                }
            }
        } elseif (is_array($configured) && array_is_list($configured)) {
            $paths = $configured;
        } else {
            throw ResourceManifestFailed::invalidConfiguration($module->moduleClass(), $plugin);
        }

        $resources = [];

        foreach ($paths as $relativePath) {
            if (! is_string($relativePath)) {
                throw ResourceManifestFailed::invalidConfiguration($module->moduleClass(), $plugin);
            }

            $path = $this->safePath($module, $plugin, $relativePath);

            if (! is_dir($path)) {
                throw ResourceManifestFailed::missingResource($module->moduleClass(), $plugin, $path);
            }

            $resources[] = new ResourceDescriptor(
                $module->moduleClass(),
                $plugin,
                $relativePath,
                $path,
            );
        }

        return $resources;
    }

    /**
     * @param array<string, mixed> $moduleConfiguration
     * @return list<ResourceDescriptor>
     */
    private function classList(
        DiscoveredModule $module,
        array $moduleConfiguration,
        string $plugin,
    ): array {
        $configured = $moduleConfiguration[$plugin] ?? [];

        if (! is_array($configured) || ! array_is_list($configured)) {
            throw ResourceManifestFailed::invalidConfiguration($module->moduleClass(), $plugin);
        }

        $resources = [];

        foreach ($configured as $class) {
            if (! is_string($class) || $class === '') {
                throw ResourceManifestFailed::invalidConfiguration($module->moduleClass(), $plugin);
            }

            $resources[] = new ResourceDescriptor(
                $module->moduleClass(),
                $plugin,
                $class,
                attributes: ['class' => $class],
            );
        }

        return $resources;
    }

    /**
     * @param array<string, mixed> $moduleConfiguration
     * @return list<ResourceDescriptor>
     */
    private function classMap(
        DiscoveredModule $module,
        array $moduleConfiguration,
        string $plugin,
    ): array {
        $configured = $moduleConfiguration[$plugin] ?? [];

        if ($configured === []) {
            return [];
        }

        if (! is_array($configured) || array_is_list($configured)) {
            throw ResourceManifestFailed::invalidConfiguration($module->moduleClass(), $plugin);
        }

        $resources = [];

        foreach ($configured as $subject => $handler) {
            if (! is_string($subject) || $subject === '' || ! is_string($handler) || $handler === '') {
                throw ResourceManifestFailed::invalidConfiguration($module->moduleClass(), $plugin);
            }

            $resources[] = new ResourceDescriptor(
                $module->moduleClass(),
                $plugin,
                $subject,
                attributes: ['subject' => $subject, 'handler' => $handler],
                collisionKey: strtolower($subject),
            );
        }

        return $resources;
    }

    /**
     * @param array<string, mixed> $moduleConfiguration
     * @return list<ResourceDescriptor>
     */
    private function listenerMap(DiscoveredModule $module, array $moduleConfiguration): array
    {
        $configured = $moduleConfiguration['listeners'] ?? [];

        if ($configured === []) {
            return [];
        }

        if (! is_array($configured) || array_is_list($configured)) {
            throw ResourceManifestFailed::invalidConfiguration($module->moduleClass(), 'listeners');
        }

        $resources = [];

        foreach ($configured as $event => $listeners) {
            if (! is_string($event) || $event === '' || ! is_array($listeners) || ! array_is_list($listeners)) {
                throw ResourceManifestFailed::invalidConfiguration($module->moduleClass(), 'listeners');
            }

            foreach ($listeners as $listener) {
                if (! is_string($listener) || $listener === '') {
                    throw ResourceManifestFailed::invalidConfiguration($module->moduleClass(), 'listeners');
                }

                $resources[] = new ResourceDescriptor(
                    $module->moduleClass(),
                    'listeners',
                    $event.'|'.$listener,
                    attributes: ['event' => $event, 'listener' => $listener],
                );
            }
        }

        return $resources;
    }

    /**
     * @param array<string, mixed> $moduleConfiguration
     * @return list<ResourceDescriptor>
     */
    private function events(DiscoveredModule $module, array $moduleConfiguration): array
    {
        $configured = $moduleConfiguration['listeners'] ?? [];

        if ($configured === []) {
            return [];
        }

        if (! is_array($configured) || array_is_list($configured)) {
            throw ResourceManifestFailed::invalidConfiguration($module->moduleClass(), 'listeners');
        }

        $resources = [];

        foreach ($configured as $event => $listeners) {
            if (! is_string($event) || $event === '' || ! is_array($listeners) || ! array_is_list($listeners)) {
                throw ResourceManifestFailed::invalidConfiguration($module->moduleClass(), 'listeners');
            }

            $resources[] = new ResourceDescriptor(
                $module->moduleClass(),
                'events',
                $event,
                attributes: ['class' => $event],
            );
        }

        return $resources;
    }

    /**
     * @param array<string, mixed> $moduleConfiguration
     * @return list<ResourceDescriptor>
     */
    private function components(DiscoveredModule $module, array $moduleConfiguration): array
    {
        if (! array_key_exists('components', $moduleConfiguration)) {
            return [];
        }

        $configured = $moduleConfiguration['components'];
        $namespace = $module->namespace().'\\View\\Components';
        $prefix = strtolower($module->name());

        if (is_array($configured)) {
            $namespace = $configured['namespace'] ?? $namespace;
            $prefix = $configured['prefix'] ?? $prefix;
        } elseif ($configured !== true) {
            throw ResourceManifestFailed::invalidConfiguration($module->moduleClass(), 'components');
        }

        if (! is_string($namespace) || $namespace === '' || ! is_string($prefix) || $prefix === '') {
            throw ResourceManifestFailed::invalidConfiguration($module->moduleClass(), 'components');
        }

        return [new ResourceDescriptor(
            $module->moduleClass(),
            'components',
            $prefix,
            dirname($module->path()).'/View/Components',
            $namespace,
            ['prefix' => $prefix],
            strtolower($prefix),
        )];
    }

    /**
     * @param array<string, mixed> $moduleConfiguration
     * @return list<ResourceDescriptor>
     */
    private function assets(DiscoveredModule $module, array $moduleConfiguration): array
    {
        $configured = $moduleConfiguration['assets'] ?? [];

        if (! is_array($configured) || ! array_is_list($configured)) {
            throw ResourceManifestFailed::invalidConfiguration($module->moduleClass(), 'assets');
        }

        $resources = [];

        foreach ($configured as $asset) {
            if (is_string($asset)) {
                $relativePath = $asset;
                $type = 'input';
                $publishTo = null;
            } elseif (is_array($asset)) {
                $relativePath = $asset['path'] ?? null;
                $type = $asset['type'] ?? 'input';
                $publishTo = $asset['publish_to'] ?? null;
            } else {
                throw ResourceManifestFailed::invalidConfiguration($module->moduleClass(), 'assets');
            }

            if (! is_string($relativePath)
                || ! is_string($type)
                || ! in_array($type, ['input', 'public'], true)
                || ($publishTo !== null && ! is_string($publishTo))
                || ($type === 'public' && ($publishTo === null || $publishTo === ''))) {
                throw ResourceManifestFailed::invalidConfiguration($module->moduleClass(), 'assets');
            }

            $resources[] = new ResourceDescriptor(
                $module->moduleClass(),
                'assets',
                $relativePath,
                $this->requiredFile($module, 'assets', $relativePath),
                attributes: ['type' => $type, 'publish_to' => $publishTo],
                collisionKey: $publishTo === null ? null : strtolower($publishTo),
            );
        }

        return $resources;
    }

    /**
     * @param array<string, mixed> $moduleConfiguration
     * @return list<ResourceDescriptor>
     */
    private function testDirectories(DiscoveredModule $module, array $moduleConfiguration): array
    {
        return $this->optInDirectories(
            $module,
            $moduleConfiguration,
            'tests',
            ['Tests', 'tests'],
        );
    }

    /**
     * @param array<string, mixed> $moduleConfiguration
     * @return list<ResourceDescriptor>
     */
    private function extensions(DiscoveredModule $module, array $moduleConfiguration): array
    {
        $configured = $moduleConfiguration['extensions'] ?? [];

        if ($configured === []) {
            return [];
        }

        if (! is_array($configured) || array_is_list($configured)) {
            throw ResourceManifestFailed::invalidConfiguration($module->moduleClass(), 'extensions');
        }

        $resources = [];

        foreach ($configured as $extension => $metadata) {
            if (! is_string($extension) || $extension === '') {
                throw ResourceManifestFailed::invalidConfiguration($module->moduleClass(), 'extensions');
            }

            $resources[] = new ResourceDescriptor(
                $module->moduleClass(),
                'extensions',
                $extension,
                attributes: ['metadata' => $metadata],
                collisionKey: strtolower($extension),
            );
        }

        return $resources;
    }

    private function requiredFile(DiscoveredModule $module, string $plugin, string $relativePath): string
    {
        $path = $this->safePath($module, $plugin, $relativePath);

        if (! is_file($path)) {
            throw ResourceManifestFailed::missingResource($module->moduleClass(), $plugin, $path);
        }

        return $path;
    }

    private function safePath(DiscoveredModule $module, string $plugin, string $relativePath): string
    {
        $normalized = str_replace('\\', '/', $relativePath);
        $segments = explode('/', $normalized);

        if ($relativePath === ''
            || str_starts_with($normalized, '/')
            || preg_match('/\A[A-Za-z]:\//', $normalized) === 1
            || in_array('..', $segments, true)
            || in_array('', $segments, true)) {
            throw ResourceManifestFailed::unsafePath($module->moduleClass(), $plugin, $relativePath);
        }

        return $this->moduleRoot($module).'/'.implode('/', $segments);
    }

    private function moduleRoot(DiscoveredModule $module): string
    {
        $entryRoot = dirname($module->path());

        if (basename($entryRoot) === 'src' && is_file(dirname($entryRoot).'/composer.json')) {
            return dirname($entryRoot);
        }

        return basename($entryRoot) === 'app' ? dirname($entryRoot) : $entryRoot;
    }
}

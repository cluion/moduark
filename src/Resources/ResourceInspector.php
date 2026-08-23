<?php

declare(strict_types=1);

namespace Cluion\Moduark\Resources;

use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Discovery\ModuleActivationSet;
use Cluion\Moduark\Lifecycle\OrderedModules;
use Cluion\Moduark\Registry\ModuleRegistry;
use Illuminate\Foundation\Application;
use InvalidArgumentException;

final readonly class ResourceInspector
{
    public function __construct(
        private ModuleRegistry $registry,
        private ResourceManifest $manifest,
        private ResourceManifestStatus $status,
        private ResourcePluginRegistry $plugins,
        private OrderedModules $ordered,
        private ModuleActivationSet $activationSet,
    ) {
    }

    /** @return list<DiscoveredModule> */
    public function modules(?string $name): array
    {
        if ($name === null) {
            return $this->registry->all();
        }

        $module = $this->registry->find($name);

        if ($module === null) {
            if ($this->activationSet->disabled($name)) {
                return [];
            }

            throw new InvalidArgumentException("Module [{$name}] is not active or does not exist.");
        }

        return [$module];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function resources(?string $name): array
    {
        $selected = array_fill_keys(array_map(
            static fn (DiscoveredModule $module): string => $module->moduleClass(),
            $this->modules($name),
        ), true);
        $resources = [];

        foreach ($this->manifest->all() as $resource) {
            if (! isset($selected[$resource->moduleClass()])) {
                continue;
            }

            $row = $resource->toArray();
            $row['enabled'] = true;
            $row['cached'] = $this->status->cached();
            $row['supported'] = $this->frameworkSupported();
            $resources[] = $row;
        }

        return $resources;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function moduleSummaries(?string $name): array
    {
        if ($name !== null && $this->registry->find($name) === null && $this->activationSet->disabled($name)) {
            return [[
                'name' => $name,
                'class' => null,
                'path' => null,
                'state' => 'disabled',
                'dependencies' => [],
                'resource_count' => 0,
            ]];
        }

        $dependencies = [];

        foreach ($this->ordered->all() as $descriptor) {
            $dependencies[$descriptor->moduleClass()] = $descriptor->dependencies();
        }

        return array_map(function (DiscoveredModule $module) use ($dependencies): array {
            return [
                'name' => $module->name(),
                'class' => $module->moduleClass(),
                'path' => $module->path(),
                'state' => 'enabled',
                'dependencies' => $dependencies[$module->moduleClass()] ?? [],
                'resource_count' => count($this->manifest->forModule($module->moduleClass())),
            ];
        }, $this->modules($name));
    }

    /**
     * @return list<array{plugin: string, collision_key: string, resources: list<array<string, mixed>>}>
     */
    public function collisions(?string $name): array
    {
        if ($name === null) {
            return $this->manifest->collisions();
        }

        $modules = $this->modules($name);

        if ($modules === []) {
            return [];
        }

        $module = $modules[0];

        return array_values(array_filter(
            $this->manifest->collisions(),
            static function (array $collision) use ($module): bool {
                foreach ($collision['resources'] as $resource) {
                    if (($resource['module'] ?? null) === $module->moduleClass()) {
                        return true;
                    }
                }

                return false;
            },
        ));
    }

    /** @return list<array{severity: string, code: string, message: string}> */
    public function issues(?string $name): array
    {
        $issues = [];

        if (! $this->frameworkSupported()) {
            $issues[] = [
                'severity' => 'error',
                'code' => 'unsupported_framework',
                'message' => 'Laravel '.Application::VERSION.' is not supported; expected major 12 or 13.',
            ];
        }

        foreach ($this->collisions($name) as $collision) {
            $issues[] = [
                'severity' => 'error',
                'code' => 'resource_collision',
                'message' => "Resource plugin [{$collision['plugin']}] collision [{$collision['collision_key']}].",
            ];
        }

        foreach ($this->resources($name) as $resource) {
            $source = $resource['source'] ?? null;
            $plugin = $resource['plugin'] ?? null;
            $identity = $resource['identity'] ?? null;

            if (is_string($source) && ! file_exists($source)
                && is_string($plugin) && is_string($identity)) {
                $issues[] = [
                    'severity' => 'error',
                    'code' => 'missing_source',
                    'message' => "Resource [{$plugin}:{$identity}] source [{$source}] is missing.",
                ];
            }

            if (is_string($plugin) && ! $this->plugins->has($plugin)) {
                $issues[] = [
                    'severity' => 'error',
                    'code' => 'missing_handler',
                    'message' => "Resource plugin [{$plugin}] has no runtime handler.",
                ];
            }
        }

        return $issues;
    }

    public function cached(): bool
    {
        return $this->status->cached();
    }

    public function frameworkSupported(): bool
    {
        $major = (int) explode('.', Application::VERSION)[0];

        return in_array($major, [12, 13], true);
    }
}

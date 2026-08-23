<?php

declare(strict_types=1);

namespace Cluion\Moduark\Resources;

use Cluion\Moduark\Exceptions\ResourceManifestFailed;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Registry\ModuleRegistry;

final readonly class ResourceManifestBuilder
{
    public function __construct(private ResourcePluginRegistry $plugins)
    {
    }

    /**
     * @param list<ModuleDescriptor> $orderedMetadata
     */
    public function build(ModuleRegistry $registry, array $orderedMetadata): ResourceManifest
    {
        $modules = [];

        foreach ($registry->all() as $module) {
            $modules[$module->moduleClass()] = $module;
        }

        $orderedClasses = array_map(
            static fn (ModuleDescriptor $descriptor): string => $descriptor->moduleClass(),
            $orderedMetadata,
        );
        $registryClasses = array_keys($modules);
        $sortedOrdered = $orderedClasses;
        sort($sortedOrdered, SORT_STRING);
        sort($registryClasses, SORT_STRING);

        if ($sortedOrdered !== $registryClasses) {
            throw ResourceManifestFailed::moduleSetMismatch();
        }

        $resources = [];

        foreach ($orderedMetadata as $metadata) {
            $moduleClass = $metadata->moduleClass();
            $module = $modules[$moduleClass];
            $configuration = ResourceData::normalizeMap(
                (new $moduleClass)->resources(),
                $moduleClass.'::resources',
            );

            foreach ($configuration as $plugin => $_configuration) {
                if (! $this->plugins->has($plugin)) {
                    throw ResourceManifestFailed::unknownPlugin($moduleClass, $plugin);
                }
            }

            foreach ($this->plugins->all() as $plugin) {
                foreach ($plugin->discoverer()->discover($module, $metadata, $configuration) as $resource) {
                    if ($resource->plugin() !== $plugin->id()) {
                        throw ResourceManifestFailed::pluginMismatch($plugin->id(), $resource->plugin());
                    }

                    if ($resource->moduleClass() !== $moduleClass) {
                        throw ResourceManifestFailed::moduleMismatch($moduleClass, $resource->moduleClass());
                    }

                    $resources[] = $resource;
                }
            }
        }

        return new ResourceManifest($orderedClasses, $resources);
    }
}

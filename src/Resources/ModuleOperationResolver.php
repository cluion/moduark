<?php

declare(strict_types=1);

namespace Cluion\Moduark\Resources;

use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Registry\ModuleRegistry;
use InvalidArgumentException;

final readonly class ModuleOperationResolver
{
    public function __construct(
        private ModuleRegistry $registry,
        private ResourceManifest $manifest,
    ) {
    }

    public function module(string $name): DiscoveredModule
    {
        $module = $this->registry->find($name);

        if ($module === null) {
            throw new InvalidArgumentException("Module [{$name}] is not active or does not exist.");
        }

        return $module;
    }

    /** @return list<ResourceDescriptor> */
    public function resources(string $name, string $plugin): array
    {
        $module = $this->module($name);

        return array_values(array_filter(
            $this->manifest->forModule($module->moduleClass()),
            static fn (ResourceDescriptor $resource): bool => $resource->plugin() === $plugin,
        ));
    }
}

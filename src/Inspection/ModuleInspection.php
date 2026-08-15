<?php

declare(strict_types=1);

namespace Cluion\Moduark\Inspection;

use Cluion\Moduark\Analysis\Source\SourceSymbol;
use Cluion\Moduark\Architecture\Level;
use Cluion\Moduark\Capability;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Graph\ModuleGraphNode;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use InvalidArgumentException;

final readonly class ModuleInspection
{
    /**
     * @param list<ModuleGraphNode> $dependencies
     * @param array<class-string<Capability>, ModuleGraphNode> $capabilityProviders
     * @param list<SourceSymbol> $publicApi
     */
    public function __construct(
        private DiscoveredModule $module,
        private Level $level,
        private ModuleDescriptor $descriptor,
        private array $dependencies,
        private array $capabilityProviders,
        private array $publicApi,
    ) {
    }

    public function module(): DiscoveredModule
    {
        return $this->module;
    }

    public function level(): Level
    {
        return $this->level;
    }

    public function descriptor(): ModuleDescriptor
    {
        return $this->descriptor;
    }

    /** @return list<ModuleGraphNode> */
    public function dependencies(): array
    {
        return $this->dependencies;
    }

    /** @return list<ModuleGraphNode> */
    public function missingDependencies(): array
    {
        return array_values(array_filter(
            $this->dependencies,
            static fn (ModuleGraphNode $dependency): bool => ! $dependency->discovered(),
        ));
    }

    /** @param class-string<Capability> $capability */
    public function capabilityProvider(string $capability): ModuleGraphNode
    {
        return $this->capabilityProviders[$capability]
            ?? throw new InvalidArgumentException(
                "Capability [{$capability}] has no resolved provider in this inspection.",
            );
    }

    /** @return list<SourceSymbol> */
    public function publicApi(): array
    {
        return $this->publicApi;
    }
}

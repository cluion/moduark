<?php

declare(strict_types=1);

namespace Cluion\Moduark\Cache;

use Cluion\Moduark\Capabilities\CapabilityResolver;
use Cluion\Moduark\Discovery\ModuleActivationSet;
use Cluion\Moduark\Discovery\ModuleDiscoverer;
use Cluion\Moduark\Lifecycle\ModuleOrderer;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Resources\ResourceManifestBuilder;

final readonly class ModuleCacheBuilder
{
    public function __construct(
        private ModuleDiscoverer $discoverer,
        private ModuleOrderer $orderer,
        private CapabilityResolver $capabilities,
        private ModuleActivationSet $activationSet,
        private ResourceManifestBuilder $resources,
    ) {
    }

    public function build(string $modulesPath): ModuleCacheManifest
    {
        $registry = $this->discoverer->discover($modulesPath, $this->activationSet);
        $descriptors = (new ModuleMetadataCompiler)->compileAll($registry->moduleClasses());
        $ordered = $this->orderer->order($descriptors);

        $this->capabilities->resolve($ordered);

        return new ModuleCacheManifest(
            $modulesPath,
            $this->activationSet->fingerprint(),
            $registry,
            $ordered,
            $this->resources->build($registry, $ordered),
        );
    }
}

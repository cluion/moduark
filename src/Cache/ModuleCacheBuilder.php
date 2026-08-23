<?php

declare(strict_types=1);

namespace Cluion\Moduark\Cache;

use Cluion\Moduark\Capabilities\CapabilityResolver;
use Cluion\Moduark\Discovery\ModuleActivationSet;
use Cluion\Moduark\Discovery\ModuleDiscoverer;
use Cluion\Moduark\Lifecycle\ModuleOrderer;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;

final readonly class ModuleCacheBuilder
{
    public function __construct(
        private ModuleDiscoverer $discoverer,
        private ModuleOrderer $orderer,
        private CapabilityResolver $capabilities,
        private ModuleActivationSet $activationSet,
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
        );
    }
}

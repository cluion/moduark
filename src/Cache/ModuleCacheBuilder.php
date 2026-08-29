<?php

declare(strict_types=1);

namespace Cluion\Moduark\Cache;

use Cluion\Moduark\Capabilities\CapabilityResolver;
use Cluion\Moduark\Discovery\ModuleActivationSet;
use Cluion\Moduark\Lifecycle\ModuleOrderer;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Registry\CanonicalModuleRegistryBuilder;
use Cluion\Moduark\Resources\ResourceManifestBuilder;

final readonly class ModuleCacheBuilder
{
    public function __construct(
        private CanonicalModuleRegistryBuilder $registry,
        private ModuleOrderer $orderer,
        private CapabilityResolver $capabilities,
        private ModuleActivationSet $activationSet,
        private ResourceManifestBuilder $resources,
    ) {
    }

    public function build(string $modulesPath): ModuleCacheManifest
    {
        $registry = $this->registry->discover($modulesPath, $this->activationSet);
        $descriptors = (new ModuleMetadataCompiler)->compileAll($registry->moduleClasses());
        $ordered = $this->orderer->order($descriptors);

        $this->capabilities->resolve($ordered);

        return new ModuleCacheManifest(
            $modulesPath,
            $this->activationSet->fingerprint(),
            $this->registry->packageFingerprint(),
            $registry,
            $ordered,
            $this->resources->build($registry, $ordered),
        );
    }
}

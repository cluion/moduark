<?php

declare(strict_types=1);

namespace Cluion\Moduark\Registry;

use Cluion\Moduark\Discovery\ModuleActivationSet;
use Cluion\Moduark\Discovery\ModuleDiscoverer;
use Cluion\Moduark\Package\PackageModuleCatalog;
use Cluion\Moduark\Package\PackageModuleDescriptor;

final readonly class CanonicalModuleRegistryBuilder
{
    public function __construct(
        private ModuleDiscoverer $applicationModules,
        private PackageModuleCatalog $packageModules,
    ) {
    }

    public function discover(
        string $modulesPath,
        ?ModuleActivationSet $activationSet = null,
    ): ModuleRegistry {
        $application = $this->applicationModules->discover($modulesPath, $activationSet);
        $packages = array_map(
            static fn (PackageModuleDescriptor $module) => $module->discoveredModule(),
            $this->packageModules->all(),
        );

        return new ModuleRegistry([...$application->all(), ...$packages]);
    }

    public function packageFingerprint(): string
    {
        return $this->packageModules->fingerprint();
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Lifecycle;

use Cluion\Moduark\Capabilities\CapabilityResolver;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Module;
use Illuminate\Contracts\Foundation\Application;

final readonly class ModuleLifecycleRegistrar
{
    public function __construct(
        private Application $application,
        private ModuleMetadataCompiler $compiler,
        private ModuleOrderer $orderer,
        private ?CapabilityResolver $capabilityResolver = null,
    ) {
    }

    /**
     * Compile and validate the complete graph before invoking any provider.
     *
     * @param list<class-string<Module>> $moduleClasses
     * @return list<ModuleDescriptor>
     */
    public function registerProviders(array $moduleClasses): array
    {
        $ordered = $this->orderer->order($this->compiler->compileAll($moduleClasses));

        ($this->capabilityResolver ?? new CapabilityResolver)->resolve($ordered);

        foreach ($ordered as $descriptor) {
            foreach ($descriptor->providers() as $provider) {
                $this->application->register($provider);
            }
        }

        return $ordered;
    }
}

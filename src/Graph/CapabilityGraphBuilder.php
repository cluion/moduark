<?php

declare(strict_types=1);

namespace Cluion\Moduark\Graph;

use Cluion\Moduark\Capabilities\CapabilityResolver;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Registry\ModuleRegistry;

final readonly class CapabilityGraphBuilder
{
    public function __construct(
        private ModuleRegistry $registry,
        private ModuleMetadataCompiler $compiler,
        private CapabilityResolver $resolver,
    ) {
    }

    public function build(): CapabilityGraph
    {
        $modules = [];

        foreach ($this->registry->all() as $module) {
            $modules[] = new ModuleGraphNode(
                $module->name(),
                $module->moduleClass(),
                $module->path(),
                true,
            );
        }

        $descriptors = $this->compiler->compileAll($this->registry->moduleClasses());

        // Use the runtime resolver as the single source of truth. A graph is
        // returned only when every required Capability has one provider and
        // every consumer Port can be bound deterministically.
        $this->resolver->resolve($descriptors);

        $capabilities = [];
        $capabilitiesByClass = [];
        $edges = [];

        foreach ($descriptors as $descriptor) {
            foreach ($descriptor->provides() as $capability) {
                $this->addCapability($capability, $capabilities, $capabilitiesByClass);
                $edges[] = new CapabilityGraphEdge(
                    CapabilityGraphEdgeType::Provides,
                    $descriptor->moduleClass(),
                    $capability,
                    $descriptor->moduleClass().'::provides()',
                );
            }

            foreach ($descriptor->requires() as $requirement) {
                $this->addCapability(
                    $requirement->capability(),
                    $capabilities,
                    $capabilitiesByClass,
                );
                $edges[] = new CapabilityGraphEdge(
                    CapabilityGraphEdgeType::Requires,
                    $descriptor->moduleClass(),
                    $requirement->capability(),
                    $descriptor->moduleClass().'::requires()',
                    $requirement->port(),
                    $requirement->adapter(),
                );
            }
        }

        return new CapabilityGraph($modules, $capabilities, $edges);
    }

    /**
     * @param class-string<\Cluion\Moduark\Capability> $capability
     * @param list<CapabilityGraphNode> $capabilities
     * @param array<class-string<\Cluion\Moduark\Capability>, true> $capabilitiesByClass
     */
    private function addCapability(
        string $capability,
        array &$capabilities,
        array &$capabilitiesByClass,
    ): void {
        if (isset($capabilitiesByClass[$capability])) {
            return;
        }

        $capabilities[] = new CapabilityGraphNode(
            $this->displayName($capability),
            $capability,
        );
        $capabilitiesByClass[$capability] = true;
    }

    private function displayName(string $capability): string
    {
        $separator = strrpos($capability, '\\');

        return $separator === false ? $capability : substr($capability, $separator + 1);
    }
}

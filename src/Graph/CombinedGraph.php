<?php

declare(strict_types=1);

namespace Cluion\Moduark\Graph;

use Cluion\Moduark\Exceptions\CombinedGraphFailed;

final readonly class CombinedGraph
{
    public function __construct(
        private ModuleGraph $moduleGraph,
        private CapabilityGraph $capabilityGraph,
    ) {
        $moduleNodes = [];
        $capabilityModules = [];

        foreach ($moduleGraph->nodes() as $node) {
            $moduleNodes[$node->moduleClass()] = $node;
        }

        foreach ($capabilityGraph->modules() as $capabilityModule) {
            $capabilityModules[$capabilityModule->moduleClass()] = true;
            $module = $moduleNodes[$capabilityModule->moduleClass()] ?? null;

            if ($module === null) {
                throw CombinedGraphFailed::missingModule($capabilityModule->moduleClass());
            }

            if (! $module->discovered()) {
                throw CombinedGraphFailed::invalidModule($capabilityModule->moduleClass());
            }

            if ($module->name() !== $capabilityModule->name()
                || $module->path() !== $capabilityModule->path()) {
                throw CombinedGraphFailed::mismatchedModule($capabilityModule->moduleClass());
            }
        }

        foreach ($moduleGraph->discoveredNodes() as $module) {
            if (! isset($capabilityModules[$module->moduleClass()])) {
                throw CombinedGraphFailed::missingCapabilityModule($module->moduleClass());
            }
        }
    }

    public function moduleGraph(): ModuleGraph
    {
        return $this->moduleGraph;
    }

    public function capabilityGraph(): CapabilityGraph
    {
        return $this->capabilityGraph;
    }

    public function neighborhood(string $module): self
    {
        $selected = null;

        foreach ($this->moduleGraph->discoveredNodes() as $node) {
            if (strcasecmp($node->name(), $module) === 0) {
                $selected = $node;

                break;
            }
        }

        if ($selected === null) {
            throw CombinedGraphFailed::unknownModule($module);
        }

        $includedModules = [$selected->moduleClass() => true];

        foreach ($this->moduleGraph->edges() as $edge) {
            if ($edge->source() !== $selected->moduleClass()
                && $edge->target() !== $selected->moduleClass()) {
                continue;
            }

            $includedModules[$edge->source()] = true;
            $includedModules[$edge->target()] = true;
        }

        $includedCapabilities = [];

        foreach ($this->capabilityGraph->edgesForModule($selected->moduleClass()) as $edge) {
            $includedCapabilities[$edge->capability()] = true;
        }

        $capabilityEdges = array_values(array_filter(
            $this->capabilityGraph->edges(),
            static fn (CapabilityGraphEdge $edge): bool => isset(
                $includedCapabilities[$edge->capability()],
            ),
        ));

        foreach ($capabilityEdges as $edge) {
            $includedModules[$edge->module()] = true;
        }

        $dependencyEdges = array_values(array_filter(
            $this->moduleGraph->edges(),
            static fn (ModuleGraphEdge $edge): bool => isset(
                $includedModules[$edge->source()],
                $includedModules[$edge->target()],
            ),
        ));
        $moduleNodes = array_values(array_filter(
            $this->moduleGraph->nodes(),
            static fn (ModuleGraphNode $node): bool => isset(
                $includedModules[$node->moduleClass()],
            ),
        ));
        $capabilityModules = array_values(array_filter(
            $this->capabilityGraph->modules(),
            static fn (ModuleGraphNode $node): bool => isset(
                $includedModules[$node->moduleClass()],
            ),
        ));
        $capabilityNodes = array_values(array_filter(
            $this->capabilityGraph->capabilities(),
            static fn (CapabilityGraphNode $node): bool => isset(
                $includedCapabilities[$node->capability()],
            ),
        ));

        return new self(
            new ModuleGraph($moduleNodes, $dependencyEdges),
            new CapabilityGraph($capabilityModules, $capabilityNodes, $capabilityEdges),
        );
    }
}

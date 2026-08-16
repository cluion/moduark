<?php

declare(strict_types=1);

namespace Cluion\Moduark\Inspection;

use Cluion\Moduark\Analysis\Boundary\PublicApi;
use Cluion\Moduark\Analysis\Source\SourceIndexBuilder;
use Cluion\Moduark\Analysis\Source\SourceSymbol;
use Cluion\Moduark\Architecture\EffectiveArchitecture;
use Cluion\Moduark\Capability;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Exceptions\ModuleInspectionFailed;
use Cluion\Moduark\Graph\CapabilityGraphEdgeType;
use Cluion\Moduark\Graph\CombinedGraphBuilder;
use Cluion\Moduark\Graph\ModuleGraphNode;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Persistence\TableOwnershipIndex;
use Cluion\Moduark\Registry\ModuleRegistry;
use LogicException;

final readonly class ModuleInspectionBuilder
{
    public function __construct(
        private ModuleRegistry $registry,
        private ModuleMetadataCompiler $compiler,
        private CombinedGraphBuilder $combinedGraphBuilder,
        private SourceIndexBuilder $sourceIndexBuilder,
        private PublicApi $publicApi,
        private EffectiveArchitecture $architecture,
    ) {
    }

    public function build(string $module): ModuleInspection
    {
        $discovered = $this->findModule($module);
        $descriptor = $this->compiler->compile($discovered->moduleClass());
        $tableOwnership = new TableOwnershipIndex(
            $this->compiler->compileAll($this->registry->moduleClasses()),
        );
        $combined = $this->combinedGraphBuilder->build();
        $moduleGraph = $combined->moduleGraph();
        $capabilityGraph = $combined->capabilityGraph();
        $dependencies = [];

        foreach ($moduleGraph->edgesFrom($discovered->moduleClass()) as $edge) {
            $dependencies[] = $moduleGraph->node($edge->target());
        }

        /** @var array<class-string<Capability>, ModuleGraphNode> $capabilityProviders */
        $capabilityProviders = [];

        foreach ($descriptor->requires() as $requirement) {
            foreach ($capabilityGraph->edgesForCapability($requirement->capability()) as $edge) {
                if ($edge->type() !== CapabilityGraphEdgeType::Provides) {
                    continue;
                }

                $capabilityProviders[$requirement->capability()] = $capabilityGraph->module(
                    $edge->module(),
                );

                break;
            }

            if (! isset($capabilityProviders[$requirement->capability()])) {
                throw new LogicException(
                    "Resolved Capability [{$requirement->capability()}] has no provider edge.",
                );
            }
        }

        $publicSymbols = array_values(array_filter(
            $this->sourceIndexBuilder->build()->symbols(),
            fn (SourceSymbol $symbol): bool => $symbol->owner() === $discovered->moduleClass()
                && $this->publicApi->includes($symbol, $discovered),
        ));

        return new ModuleInspection(
            $discovered,
            $this->architecture->level(),
            $descriptor,
            $dependencies,
            $capabilityProviders,
            $publicSymbols,
            $tableOwnership->tablesFor($discovered->moduleClass()),
        );
    }

    private function findModule(string $module): DiscoveredModule
    {
        foreach ($this->registry->all() as $candidate) {
            if (strcasecmp($candidate->name(), $module) === 0) {
                return $candidate;
            }
        }

        throw ModuleInspectionFailed::unknownModule($module);
    }
}

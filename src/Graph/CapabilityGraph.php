<?php

declare(strict_types=1);

namespace Cluion\Moduark\Graph;

use Cluion\Moduark\Capability;
use Cluion\Moduark\Exceptions\CapabilityGraphFailed;
use Cluion\Moduark\Module;

final readonly class CapabilityGraph
{
    /** @var list<ModuleGraphNode> */
    private array $modules;

    /** @var array<class-string<Module>, ModuleGraphNode> */
    private array $modulesByClass;

    /** @var list<CapabilityGraphNode> */
    private array $capabilities;

    /** @var array<class-string<Capability>, CapabilityGraphNode> */
    private array $capabilitiesByClass;

    /** @var list<CapabilityGraphEdge> */
    private array $edges;

    /**
     * @param list<ModuleGraphNode> $modules
     * @param list<CapabilityGraphNode> $capabilities
     * @param list<CapabilityGraphEdge> $edges
     */
    public function __construct(array $modules, array $capabilities, array $edges)
    {
        $modulesByClass = [];

        foreach ($modules as $module) {
            $moduleClass = $module->moduleClass();

            if (isset($modulesByClass[$moduleClass])) {
                throw CapabilityGraphFailed::duplicateModule($moduleClass);
            }

            if (! $module->discovered()) {
                throw CapabilityGraphFailed::undiscoveredModule($moduleClass);
            }

            $modulesByClass[$moduleClass] = $module;
        }

        $capabilitiesByClass = [];

        foreach ($capabilities as $capability) {
            $capabilityClass = $capability->capability();

            if (isset($capabilitiesByClass[$capabilityClass])) {
                throw CapabilityGraphFailed::duplicateCapability($capabilityClass);
            }

            $capabilitiesByClass[$capabilityClass] = $capability;
        }

        $edgeKeys = [];

        foreach ($edges as $edge) {
            if (! isset($modulesByClass[$edge->module()])) {
                throw CapabilityGraphFailed::missingModuleEndpoint($edge->module());
            }

            if (! isset($capabilitiesByClass[$edge->capability()])) {
                throw CapabilityGraphFailed::missingCapabilityEndpoint($edge->capability());
            }

            $key = implode("\0", [
                $edge->type()->value,
                $edge->module(),
                $edge->capability(),
            ]);

            if (isset($edgeKeys[$key])) {
                throw CapabilityGraphFailed::duplicateEdge(
                    $edge->type()->value,
                    $edge->module(),
                    $edge->capability(),
                );
            }

            $edgeKeys[$key] = true;
        }

        usort($modules, static function (ModuleGraphNode $left, ModuleGraphNode $right): int {
            $byName = strcasecmp($left->name(), $right->name());

            if ($byName !== 0) {
                return $byName;
            }

            $byExactName = strcmp($left->name(), $right->name());

            return $byExactName !== 0
                ? $byExactName
                : strcmp($left->moduleClass(), $right->moduleClass());
        });

        usort($capabilities, static function (
            CapabilityGraphNode $left,
            CapabilityGraphNode $right,
        ): int {
            $byName = strcasecmp($left->name(), $right->name());

            if ($byName !== 0) {
                return $byName;
            }

            $byExactName = strcmp($left->name(), $right->name());

            return $byExactName !== 0
                ? $byExactName
                : strcmp($left->capability(), $right->capability());
        });

        usort($edges, static function (CapabilityGraphEdge $left, CapabilityGraphEdge $right) use (
            $modulesByClass,
            $capabilitiesByClass,
        ): int {
            $leftCapability = $capabilitiesByClass[$left->capability()]->name();
            $rightCapability = $capabilitiesByClass[$right->capability()]->name();
            $byCapability = strcasecmp($leftCapability, $rightCapability);

            if ($byCapability !== 0) {
                return $byCapability;
            }

            $typeOrder = [
                CapabilityGraphEdgeType::Provides->value => 0,
                CapabilityGraphEdgeType::Requires->value => 1,
            ];
            $byType = $typeOrder[$left->type()->value] <=> $typeOrder[$right->type()->value];

            if ($byType !== 0) {
                return $byType;
            }

            $leftModule = $modulesByClass[$left->module()]->name();
            $rightModule = $modulesByClass[$right->module()]->name();
            $byModule = strcasecmp($leftModule, $rightModule);

            if ($byModule !== 0) {
                return $byModule;
            }

            $byModuleClass = strcmp($left->module(), $right->module());

            return $byModuleClass !== 0
                ? $byModuleClass
                : strcmp($left->capability(), $right->capability());
        });

        $this->modules = $modules;
        $this->modulesByClass = $modulesByClass;
        $this->capabilities = $capabilities;
        $this->capabilitiesByClass = $capabilitiesByClass;
        $this->edges = $edges;
    }

    /** @return list<ModuleGraphNode> */
    public function modules(): array
    {
        return $this->modules;
    }

    /** @return list<CapabilityGraphNode> */
    public function capabilities(): array
    {
        return $this->capabilities;
    }

    /** @return list<CapabilityGraphEdge> */
    public function edges(): array
    {
        return $this->edges;
    }

    /** @param class-string<Module> $moduleClass */
    public function module(string $moduleClass): ModuleGraphNode
    {
        return $this->modulesByClass[$moduleClass]
            ?? throw CapabilityGraphFailed::missingModuleEndpoint($moduleClass);
    }

    /** @param class-string<Capability> $capability */
    public function capability(string $capability): CapabilityGraphNode
    {
        return $this->capabilitiesByClass[$capability]
            ?? throw CapabilityGraphFailed::missingCapabilityEndpoint($capability);
    }

    /**
     * @param class-string<Capability> $capability
     * @return list<CapabilityGraphEdge>
     */
    public function edgesForCapability(string $capability): array
    {
        return array_values(array_filter(
            $this->edges,
            static fn (CapabilityGraphEdge $edge): bool => $edge->capability() === $capability,
        ));
    }

    /**
     * @param class-string<Module> $moduleClass
     * @return list<CapabilityGraphEdge>
     */
    public function edgesForModule(string $moduleClass): array
    {
        return array_values(array_filter(
            $this->edges,
            static fn (CapabilityGraphEdge $edge): bool => $edge->module() === $moduleClass,
        ));
    }

    public function neighborhood(string $module): self
    {
        $selected = null;

        foreach ($this->modules as $node) {
            if (strcasecmp($node->name(), $module) === 0) {
                $selected = $node;

                break;
            }
        }

        if ($selected === null) {
            throw CapabilityGraphFailed::unknownModule($module);
        }

        $includedModules = [$selected->moduleClass() => true];
        $includedCapabilities = [];

        foreach ($this->edgesForModule($selected->moduleClass()) as $edge) {
            $includedCapabilities[$edge->capability()] = true;
        }

        $includedEdges = array_values(array_filter(
            $this->edges,
            static function (CapabilityGraphEdge $edge) use (
                $includedCapabilities,
            ): bool {
                return isset($includedCapabilities[$edge->capability()]);
            },
        ));

        foreach ($includedEdges as $edge) {
            $includedModules[$edge->module()] = true;
        }

        $modules = array_values(array_filter(
            $this->modules,
            static fn (ModuleGraphNode $node): bool => isset(
                $includedModules[$node->moduleClass()],
            ),
        ));
        $capabilities = array_values(array_filter(
            $this->capabilities,
            static fn (CapabilityGraphNode $node): bool => isset(
                $includedCapabilities[$node->capability()],
            ),
        ));

        return new self($modules, $capabilities, $includedEdges);
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Graph;

use Cluion\Moduark\Exceptions\ModuleGraphFailed;
use Cluion\Moduark\Module;

final readonly class ModuleGraph
{
    /** @var list<ModuleGraphNode> */
    private array $nodes;

    /** @var array<class-string<Module>, ModuleGraphNode> */
    private array $nodesByClass;

    /** @var list<ModuleGraphEdge> */
    private array $edges;

    /**
     * @param list<ModuleGraphNode> $nodes
     * @param list<ModuleGraphEdge> $edges
     */
    public function __construct(array $nodes, array $edges)
    {
        $nodesByClass = [];

        foreach ($nodes as $node) {
            $moduleClass = $node->moduleClass();

            if (isset($nodesByClass[$moduleClass])) {
                throw ModuleGraphFailed::duplicateNode($moduleClass);
            }

            $nodesByClass[$moduleClass] = $node;
        }

        $edgeKeys = [];

        foreach ($edges as $edge) {
            $source = $nodesByClass[$edge->source()] ?? null;

            if ($source === null) {
                throw ModuleGraphFailed::missingEndpoint($edge->source());
            }

            if (! $source->discovered()) {
                throw ModuleGraphFailed::invalidSource($edge->source());
            }

            if (! isset($nodesByClass[$edge->target()])) {
                throw ModuleGraphFailed::missingEndpoint($edge->target());
            }

            $key = $edge->source()."\0".$edge->target();

            if (isset($edgeKeys[$key])) {
                throw ModuleGraphFailed::duplicateEdge($edge->source(), $edge->target());
            }

            $edgeKeys[$key] = true;
        }

        usort($nodes, static function (ModuleGraphNode $left, ModuleGraphNode $right): int {
            $byName = strcasecmp($left->name(), $right->name());

            if ($byName !== 0) {
                return $byName;
            }

            $byExactName = strcmp($left->name(), $right->name());

            return $byExactName !== 0
                ? $byExactName
                : strcmp($left->moduleClass(), $right->moduleClass());
        });

        usort($edges, static function (ModuleGraphEdge $left, ModuleGraphEdge $right) use ($nodesByClass): int {
            $leftSource = $nodesByClass[$left->source()]->name();
            $rightSource = $nodesByClass[$right->source()]->name();
            $bySource = strcasecmp($leftSource, $rightSource);

            if ($bySource !== 0) {
                return $bySource;
            }

            $leftTarget = $nodesByClass[$left->target()]->name();
            $rightTarget = $nodesByClass[$right->target()]->name();
            $byTarget = strcasecmp($leftTarget, $rightTarget);

            if ($byTarget !== 0) {
                return $byTarget;
            }

            $bySourceClass = strcmp($left->source(), $right->source());

            return $bySourceClass !== 0
                ? $bySourceClass
                : strcmp($left->target(), $right->target());
        });

        $this->nodes = $nodes;
        $this->nodesByClass = $nodesByClass;
        $this->edges = $edges;
    }

    /**
     * @return list<ModuleGraphNode>
     */
    public function nodes(): array
    {
        return $this->nodes;
    }

    /**
     * @return list<ModuleGraphNode>
     */
    public function discoveredNodes(): array
    {
        return array_values(array_filter(
            $this->nodes,
            static fn (ModuleGraphNode $node): bool => $node->discovered(),
        ));
    }

    /**
     * @return list<ModuleGraphEdge>
     */
    public function edges(): array
    {
        return $this->edges;
    }

    /**
     * @param class-string<Module> $moduleClass
     */
    public function node(string $moduleClass): ModuleGraphNode
    {
        return $this->nodesByClass[$moduleClass]
            ?? throw ModuleGraphFailed::missingEndpoint($moduleClass);
    }

    /**
     * @param class-string<Module> $source
     * @return list<ModuleGraphEdge>
     */
    public function edgesFrom(string $source): array
    {
        return array_values(array_filter(
            $this->edges,
            static fn (ModuleGraphEdge $edge): bool => $edge->source() === $source,
        ));
    }

    public function neighborhood(string $module): self
    {
        $selected = null;

        foreach ($this->discoveredNodes() as $node) {
            if (strcasecmp($node->name(), $module) === 0) {
                $selected = $node;

                break;
            }
        }

        if ($selected === null) {
            throw ModuleGraphFailed::unknownModule($module);
        }

        $includedClasses = [$selected->moduleClass() => true];
        $includedEdges = [];

        foreach ($this->edges as $edge) {
            if ($edge->source() !== $selected->moduleClass()
                && $edge->target() !== $selected->moduleClass()) {
                continue;
            }

            $includedClasses[$edge->source()] = true;
            $includedClasses[$edge->target()] = true;
            $includedEdges[] = $edge;
        }

        $includedNodes = array_values(array_filter(
            $this->nodes,
            static fn (ModuleGraphNode $node): bool => isset($includedClasses[$node->moduleClass()]),
        ));

        return new self($includedNodes, $includedEdges);
    }
}

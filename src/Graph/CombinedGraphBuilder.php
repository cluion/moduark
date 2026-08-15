<?php

declare(strict_types=1);

namespace Cluion\Moduark\Graph;

final readonly class CombinedGraphBuilder
{
    public function __construct(
        private ModuleGraphBuilder $moduleBuilder,
        private CapabilityGraphBuilder $capabilityBuilder,
    ) {
    }

    public function build(): CombinedGraph
    {
        return new CombinedGraph(
            $this->moduleBuilder->build(),
            $this->capabilityBuilder->build(),
        );
    }
}

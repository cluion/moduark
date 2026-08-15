<?php

declare(strict_types=1);

namespace Cluion\Moduark\Graph\Export;

use Cluion\Moduark\Graph\ModuleGraph;
use Cluion\Moduark\Graph\ModuleGraphEdge;

final class TextModuleGraphExporter
{
    public function export(ModuleGraph $graph): string
    {
        $lines = [];

        foreach ($graph->discoveredNodes() as $node) {
            $dependencies = array_map(
                function (ModuleGraphEdge $edge) use ($graph): string {
                    $target = $graph->node($edge->target());

                    return $target->name().($target->discovered() ? '' : ' [missing]');
                },
                $graph->edgesFrom($node->moduleClass()),
            );

            $lines[] = sprintf(
                '%s -> %s',
                $node->name(),
                $dependencies === [] ? '—' : implode(', ', $dependencies),
            );
        }

        return implode(PHP_EOL, $lines);
    }
}

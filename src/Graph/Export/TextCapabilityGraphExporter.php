<?php

declare(strict_types=1);

namespace Cluion\Moduark\Graph\Export;

use Cluion\Moduark\Graph\CapabilityGraph;
use Cluion\Moduark\Graph\CapabilityGraphEdge;

final class TextCapabilityGraphExporter
{
    public function export(CapabilityGraph $graph): string
    {
        $lines = [];

        foreach ($graph->modules() as $module) {
            $edges = $graph->edgesForModule($module->moduleClass());

            if ($edges === []) {
                $lines[] = $module->name().' -> —';

                continue;
            }

            foreach ($edges as $edge) {
                $lines[] = sprintf(
                    '%s -[%s]-> %s',
                    $module->name(),
                    $edge->type()->value,
                    $this->capabilityName($graph, $edge),
                );
            }
        }

        return implode(PHP_EOL, $lines);
    }

    private function capabilityName(
        CapabilityGraph $graph,
        CapabilityGraphEdge $edge,
    ): string {
        return $graph->capability($edge->capability())->name();
    }
}

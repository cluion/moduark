<?php

declare(strict_types=1);

namespace Cluion\Moduark\Graph\Export;

use Cluion\Moduark\Graph\CombinedGraph;

final class TextCombinedGraphExporter
{
    public function export(CombinedGraph $graph): string
    {
        $moduleGraph = $graph->moduleGraph();
        $capabilityGraph = $graph->capabilityGraph();
        $lines = [];

        foreach ($moduleGraph->discoveredNodes() as $module) {
            $hasRelationship = false;

            foreach ($moduleGraph->edgesFrom($module->moduleClass()) as $edge) {
                $target = $moduleGraph->node($edge->target());
                $lines[] = sprintf(
                    '%s -[depends]-> %s%s',
                    $module->name(),
                    $target->name(),
                    $target->discovered() ? '' : ' [missing]',
                );
                $hasRelationship = true;
            }

            foreach ($capabilityGraph->edgesForModule($module->moduleClass()) as $edge) {
                $lines[] = sprintf(
                    '%s -[%s]-> %s',
                    $module->name(),
                    $edge->type()->value,
                    $capabilityGraph->capability($edge->capability())->name(),
                );
                $hasRelationship = true;
            }

            if (! $hasRelationship) {
                $lines[] = $module->name().' -> —';
            }
        }

        return implode(PHP_EOL, $lines);
    }
}

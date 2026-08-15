<?php

declare(strict_types=1);

namespace Cluion\Moduark\Graph\Export;

use Cluion\Moduark\Graph\CombinedGraph;

final class MermaidCombinedGraphExporter
{
    public function export(CombinedGraph $graph): string
    {
        $moduleGraph = $graph->moduleGraph();
        $capabilityGraph = $graph->capabilityGraph();
        $lines = ['flowchart LR'];
        $moduleIdentifiers = [];
        $capabilityIdentifiers = [];
        $hasMissingModule = false;

        foreach ($moduleGraph->nodes() as $index => $module) {
            $identifier = 'M'.$index;
            $moduleIdentifiers[$module->moduleClass()] = $identifier;
            $label = $module->name();
            $style = '';

            if (! $module->discovered()) {
                $label .= ' (missing)';
                $style = ':::missing';
                $hasMissingModule = true;
            }

            $lines[] = sprintf(
                '    %s["%s"]%s',
                $identifier,
                $this->escapeLabel($label),
                $style,
            );
        }

        foreach ($capabilityGraph->capabilities() as $index => $capability) {
            $identifier = 'C'.$index;
            $capabilityIdentifiers[$capability->capability()] = $identifier;
            $lines[] = sprintf(
                '    %s(["%s"])',
                $identifier,
                $this->escapeLabel($capability->name()),
            );
        }

        foreach ($moduleGraph->edges() as $edge) {
            $lines[] = sprintf(
                '    %s -->|"depends"| %s',
                $moduleIdentifiers[$edge->source()],
                $moduleIdentifiers[$edge->target()],
            );
        }

        foreach ($capabilityGraph->edges() as $edge) {
            $lines[] = sprintf(
                '    %s -->|"%s"| %s',
                $moduleIdentifiers[$edge->module()],
                $edge->type()->value,
                $capabilityIdentifiers[$edge->capability()],
            );
        }

        if ($hasMissingModule) {
            $lines[] = '    classDef missing fill:#fff3cd,stroke:#d39e00,stroke-dasharray: 5 5';
        }

        return implode(PHP_EOL, $lines);
    }

    private function escapeLabel(string $label): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $label);
    }
}

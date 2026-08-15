<?php

declare(strict_types=1);

namespace Cluion\Moduark\Graph\Export;

use Cluion\Moduark\Graph\ModuleGraph;

final class MermaidModuleGraphExporter
{
    public function export(ModuleGraph $graph): string
    {
        $lines = ['flowchart LR'];
        $identifiers = [];
        $hasMissingNode = false;

        foreach ($graph->nodes() as $index => $node) {
            $identifier = 'M'.$index;
            $identifiers[$node->moduleClass()] = $identifier;
            $label = $node->name();
            $style = '';

            if (! $node->discovered()) {
                $label .= ' (missing)';
                $style = ':::missing';
                $hasMissingNode = true;
            }

            $lines[] = sprintf(
                '    %s["%s"]%s',
                $identifier,
                $this->escapeLabel($label),
                $style,
            );
        }

        foreach ($graph->edges() as $edge) {
            $lines[] = sprintf(
                '    %s --> %s',
                $identifiers[$edge->source()],
                $identifiers[$edge->target()],
            );
        }

        if ($hasMissingNode) {
            $lines[] = '    classDef missing fill:#fff3cd,stroke:#d39e00,stroke-dasharray: 5 5';
        }

        return implode(PHP_EOL, $lines);
    }

    private function escapeLabel(string $label): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $label);
    }
}

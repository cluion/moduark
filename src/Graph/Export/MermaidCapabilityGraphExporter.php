<?php

declare(strict_types=1);

namespace Cluion\Moduark\Graph\Export;

use Cluion\Moduark\Graph\CapabilityGraph;

final class MermaidCapabilityGraphExporter
{
    public function export(CapabilityGraph $graph): string
    {
        $lines = ['flowchart LR'];
        $moduleIdentifiers = [];
        $capabilityIdentifiers = [];

        foreach ($graph->modules() as $index => $module) {
            $identifier = 'M'.$index;
            $moduleIdentifiers[$module->moduleClass()] = $identifier;
            $lines[] = sprintf(
                '    %s["%s"]',
                $identifier,
                $this->escapeLabel($module->name()),
            );
        }

        foreach ($graph->capabilities() as $index => $capability) {
            $identifier = 'C'.$index;
            $capabilityIdentifiers[$capability->capability()] = $identifier;
            $lines[] = sprintf(
                '    %s(["%s"])',
                $identifier,
                $this->escapeLabel($capability->name()),
            );
        }

        foreach ($graph->edges() as $edge) {
            $lines[] = sprintf(
                '    %s -->|"%s"| %s',
                $moduleIdentifiers[$edge->module()],
                $edge->type()->value,
                $capabilityIdentifiers[$edge->capability()],
            );
        }

        return implode(PHP_EOL, $lines);
    }

    private function escapeLabel(string $label): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $label);
    }
}

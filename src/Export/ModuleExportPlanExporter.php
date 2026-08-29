<?php

declare(strict_types=1);

namespace Cluion\Moduark\Export;

final class ModuleExportPlanExporter
{
    public function json(ModuleExportPlan $plan, int $exitCode): string
    {
        return $this->encode([
            ...$plan->toArray(),
            'exit_code' => $exitCode,
            'error' => null,
        ]);
    }

    public function jsonFailure(int $exitCode, string $message): string
    {
        return $this->encode([
            'schema_version' => ModuleExportPlan::SCHEMA_VERSION,
            'status' => 'error',
            'complete' => false,
            'operation' => 'export',
            'dry_run' => true,
            'module' => null,
            'package' => null,
            'summary' => [
                'files' => 0,
                'dependencies' => 0,
                'manual_dependencies' => 0,
                'blockers' => 0,
            ],
            'files' => [],
            'dependencies' => [],
            'blockers' => [],
            'exit_code' => $exitCode,
            'error' => $message,
        ]);
    }

    /** @return list<string> */
    public function textLines(ModuleExportPlan $plan): array
    {
        $lines = [];

        foreach ($plan->files() as $file) {
            $values = $file->toArray();
            $lines[] = strtoupper($values['operation']).' '.$values['destination'];
        }

        foreach ($plan->dependencies() as $dependency) {
            $values = $dependency->toArray();
            $requirement = $values['package'] === null
                ? '[manual]'
                : $values['package'].' '.$values['constraint'];
            $lines[] = 'REQUIRE '.$values['source'].' => '.$requirement;
        }

        foreach ($plan->blockers() as $blocker) {
            $values = $blocker->toArray();
            $lines[] = 'BLOCK '.$values['code'].' '.implode(', ', $values['evidence']);
        }

        return $lines;
    }

    /** @param array<string, mixed> $payload */
    private function encode(array $payload): string
    {
        return json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }
}

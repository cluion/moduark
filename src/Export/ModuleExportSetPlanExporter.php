<?php

declare(strict_types=1);

namespace Cluion\Moduark\Export;

final class ModuleExportSetPlanExporter
{
    public function json(ModuleExportSetPlan $plan, int $exitCode): string
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
            'schema_version' => ModuleExportSetPlan::SCHEMA_VERSION,
            'status' => 'error',
            'complete' => false,
            'operation' => 'export-set',
            'dry_run' => true,
            'order' => [],
            'summary' => [
                'packages' => 0,
                'ready_packages' => 0,
                'files' => 0,
                'dependencies' => 0,
                'blockers' => 0,
            ],
            'packages' => [],
            'blockers' => [],
            'exit_code' => $exitCode,
            'error' => $message,
        ]);
    }

    /** @return list<string> */
    public function textLines(ModuleExportSetPlan $plan): array
    {
        $lines = [];

        foreach ($plan->packages() as $index => $package) {
            $modulePlan = $package->plan();
            $lines[] = sprintf(
                'PACKAGE %d %s => %s %s @ %s',
                $index + 1,
                $modulePlan->module()->name(),
                $modulePlan->package(),
                $package->constraint(),
                $modulePlan->target(),
            );

            foreach ($modulePlan->blockers() as $blocker) {
                $values = $blocker->toArray();
                $lines[] = sprintf(
                    'BLOCK %s/%s %s',
                    $modulePlan->module()->name(),
                    $values['code'],
                    implode(', ', $values['evidence']),
                );
            }
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

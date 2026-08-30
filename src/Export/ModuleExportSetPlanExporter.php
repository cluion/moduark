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

    public function jsonFailure(int $exitCode, string $message, bool $dryRun = true): string
    {
        return $this->encode([
            'schema_version' => ModuleExportSetPlan::SCHEMA_VERSION,
            'status' => 'error',
            'complete' => false,
            'operation' => 'export-set',
            'dry_run' => $dryRun,
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
            ...($dryRun ? [] : [
                'published_targets' => [],
                'published_before_rollback' => [],
                'remaining_targets' => [],
                'rollback_failures' => [],
            ]),
            'exit_code' => $exitCode,
            'error' => $message,
        ]);
    }

    public function jsonMaterialized(
        ModuleExportSetPlan $plan,
        ModuleExportSetExecutionResult $result,
    ): string {
        return $this->encode([
            ...$plan->toArray(),
            'status' => 'exported',
            'dry_run' => false,
            'published_targets' => $result->publishedTargets(),
            'published_before_rollback' => [],
            'remaining_targets' => [],
            'rollback_failures' => [],
            'exit_code' => 0,
            'error' => null,
        ]);
    }

    public function jsonMaterializationBlocked(ModuleExportSetPlan $plan): string
    {
        return $this->encode([
            ...$plan->toArray(),
            'dry_run' => false,
            'published_targets' => [],
            'published_before_rollback' => [],
            'remaining_targets' => [],
            'rollback_failures' => [],
            'exit_code' => 1,
            'error' => null,
        ]);
    }

    public function jsonMaterializationFailure(
        ModuleExportSetPlan $plan,
        ModuleExportSetExecutionResult $result,
    ): string {
        return $this->encode([
            ...$plan->toArray(),
            'status' => 'error',
            'complete' => false,
            'dry_run' => false,
            'published_targets' => [],
            'published_before_rollback' => $result->publishedBeforeRollback(),
            'remaining_targets' => $result->remainingTargets(),
            'rollback_failures' => $result->rollbackFailures(),
            'exit_code' => 2,
            'error' => $result->error(),
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

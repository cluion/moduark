<?php

declare(strict_types=1);

namespace Cluion\Moduark\Export;

use Throwable;

final readonly class ModuleExportSetMaterializer
{
    public function __construct(
        private ModuleExportFilesystem $filesystem,
        private ModuleExportPackagePreparer $preparer,
        private ModuleExportTargetGuard $targetGuard,
    ) {
    }

    public function materialize(ModuleExportSetPlan $plan): ModuleExportSetExecutionResult
    {
        if (! $plan->ready()) {
            return ModuleExportSetExecutionResult::failure(
                'A blocked package-set export plan cannot be materialized.',
            );
        }

        $records = [];
        $createdParents = [];
        $published = [];

        try {
            $transaction = bin2hex(random_bytes(8));

            foreach ($plan->packages() as $package) {
                $modulePlan = $package->plan();
                $target = $this->targetGuard->absoluteTarget($modulePlan);
                $parent = dirname($target);
                $staging = $parent.'/.moduark-export-set-'.$transaction
                    .'-'.strtolower($modulePlan->module()->name());
                $this->targetGuard->assertAvailable($target);
                $createdParents = [
                    ...$createdParents,
                    ...$this->targetGuard->missingParents($parent),
                ];
                $records[] = [
                    'plan' => $modulePlan,
                    'target' => $target,
                    'relative' => $modulePlan->target(),
                    'parent' => $parent,
                    'staging' => $staging,
                ];
            }

            foreach ($records as $record) {
                $this->filesystem->ensureDirectory($record['parent']);
                $this->targetGuard->assertAvailable($record['target']);
                $this->filesystem->ensureDirectory($record['staging']);
                $this->preparer->prepare($record['plan'], $record['staging']);
            }

            foreach ($records as $record) {
                $this->targetGuard->assertAvailable($record['target']);
            }

            foreach ($records as $record) {
                $this->filesystem->moveDirectory($record['staging'], $record['target']);
                $published[] = $record;
            }
        } catch (Throwable $exception) {
            [$remaining, $failures] = $this->rollback($records, $published, $createdParents);

            return ModuleExportSetExecutionResult::failure(
                $exception->getMessage(),
                array_column($published, 'relative'),
                $remaining,
                $failures,
            );
        }

        return ModuleExportSetExecutionResult::success(array_column($published, 'relative'));
    }

    /**
     * @param list<array{plan: ModuleExportPlan, target: string, relative: string, parent: string, staging: string}> $records
     * @param list<array{plan: ModuleExportPlan, target: string, relative: string, parent: string, staging: string}> $published
     * @param list<string> $createdParents
     * @return array{list<string>, list<string>}
     */
    private function rollback(array $records, array $published, array $createdParents): array
    {
        $remaining = [];
        $failures = [];

        foreach (array_reverse($records) as $record) {
            try {
                $this->filesystem->delete($record['staging']);
            } catch (Throwable) {
                $failures[] = $record['staging'];
            }
        }

        foreach (array_reverse($published) as $record) {
            try {
                $this->filesystem->delete($record['target']);
            } catch (Throwable) {
                $remaining[] = $record['relative'];
                $failures[] = $record['target'];
            }
        }

        $createdParents = array_values(array_unique($createdParents));
        usort(
            $createdParents,
            static fn (string $left, string $right): int => strlen($right) <=> strlen($left),
        );

        foreach ($createdParents as $parent) {
            try {
                $this->filesystem->removeEmptyDirectory($parent);
            } catch (Throwable) {
                $failures[] = $parent;
            }
        }

        return [array_reverse($remaining), $failures];
    }
}

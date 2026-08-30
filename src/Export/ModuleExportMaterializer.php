<?php

declare(strict_types=1);

namespace Cluion\Moduark\Export;

use Throwable;

final readonly class ModuleExportMaterializer
{
    public function __construct(
        private ModuleExportFilesystem $filesystem,
        private ModuleExportPackagePreparer $preparer,
        private ModuleExportTargetGuard $targetGuard,
    ) {
    }

    public function materialize(ModuleExportPlan $plan): ModuleExportExecutionResult
    {
        if (! $plan->ready()) {
            return ModuleExportExecutionResult::failure('A blocked export plan cannot be materialized.');
        }

        $target = $this->targetGuard->absoluteTarget($plan);
        $parent = dirname($target);
        $createdParents = $this->targetGuard->missingParents($parent);
        $staging = null;

        try {
            $staging = $parent.'/.moduark-export-'.strtolower($plan->module()->name()).'-'.bin2hex(random_bytes(8));
            $this->targetGuard->assertAvailable($target);
            $this->filesystem->ensureDirectory($parent);
            $this->targetGuard->assertAvailable($target);
            $this->filesystem->ensureDirectory($staging);
            $this->preparer->prepare($plan, $staging);
            $this->targetGuard->assertAvailable($target);
            $this->filesystem->moveDirectory($staging, $target);
        } catch (Throwable $exception) {
            return ModuleExportExecutionResult::failure(
                $exception->getMessage(),
                $this->rollback($staging, $createdParents),
            );
        }

        return ModuleExportExecutionResult::success();
    }

    /**
     * @param list<string> $createdParents
     * @return list<string>
     */
    private function rollback(?string $staging, array $createdParents): array
    {
        $failures = [];

        if ($staging !== null) {
            try {
                $this->filesystem->delete($staging);
            } catch (Throwable) {
                $failures[] = $staging;
            }
        }

        foreach ($createdParents as $parent) {
            try {
                $this->filesystem->removeEmptyDirectory($parent);
            } catch (Throwable) {
                $failures[] = $parent;
            }
        }

        return $failures;
    }
}

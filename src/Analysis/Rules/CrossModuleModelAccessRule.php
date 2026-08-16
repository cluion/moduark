<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Rules;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\ArchitectureRule;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Architecture\Violation;
use LogicException;

final class CrossModuleModelAccessRule implements ArchitectureRule
{
    public function id(): RuleId
    {
        return RuleId::CrossModuleModelAccess;
    }

    public function inspect(AnalysisContext $context, Severity $severity): RuleResult
    {
        $violations = [];
        $sourceIndex = $context->sourceIndex();

        foreach ($sourceIndex->references() as $reference) {
            if ($reference->source() === $reference->target()) {
                continue;
            }

            $model = $sourceIndex->symbol($reference->symbol());

            if ($model === null) {
                throw new LogicException(
                    "Source reference [{$reference->symbol()}] has no Model ownership candidate.",
                );
            }

            if (! $sourceIndex->isEloquentModel($model)) {
                continue;
            }

            $consumerName = $context->displayName($reference->source());
            $targetName = $context->displayName($reference->target());

            $violations[] = new Violation(
                $this->id(),
                'MOD-MODEL-001',
                $severity,
                "Module [{$consumerName}] directly accesses an Eloquent Model owned by Module [{$targetName}].",
                $reference->file(),
                $reference->line(),
                $consumerName,
                $targetName,
                $reference->symbol(),
                "Keep only the {$targetName} identifier and access its data through a Port or exported boundary.",
            );
        }

        return new RuleResult($this->id(), $violations);
    }
}

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

final class UndeclaredDependenciesRule implements ArchitectureRule
{
    public function id(): RuleId
    {
        return RuleId::UndeclaredDependencies;
    }

    public function inspect(AnalysisContext $context, Severity $severity): RuleResult
    {
        $violations = [];
        $reportedPairs = [];

        foreach ($context->sourceIndex()->references() as $reference) {
            if ($reference->source() === $reference->target()) {
                continue;
            }

            $descriptor = $context->descriptor($reference->source());

            if ($descriptor === null) {
                throw new LogicException(
                    "Source owner [{$reference->source()}] has no Module descriptor.",
                );
            }

            if (in_array($reference->target(), $descriptor->dependencies(), true)) {
                continue;
            }

            $pair = $reference->source()."\0".$reference->target();

            if (isset($reportedPairs[$pair])) {
                continue;
            }

            $reportedPairs[$pair] = true;

            $consumer = $context->module($reference->source());
            $target = $context->module($reference->target());
            $consumerName = $consumer?->name() ?? $context->displayName($reference->source());
            $targetName = $target?->name() ?? $context->displayName($reference->target());

            $violations[] = new Violation(
                $this->id(),
                'MOD-DEPENDENCY-002',
                $severity,
                "Module [{$consumerName}] uses [{$targetName}] without declaring the dependency.",
                $reference->file(),
                $reference->line(),
                $consumerName,
                $targetName,
                $reference->symbol(),
                "Declare Module [{$targetName}] in {$consumerName}Module::dependencies() or remove the cross-Module reference.",
            );
        }

        return new RuleResult($this->id(), $violations);
    }
}

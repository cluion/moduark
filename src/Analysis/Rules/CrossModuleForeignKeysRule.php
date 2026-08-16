<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Rules;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\ArchitectureRule;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Architecture\Violation;

final class CrossModuleForeignKeysRule implements ArchitectureRule
{
    public function id(): RuleId
    {
        return RuleId::CrossModuleForeignKeys;
    }

    public function inspect(AnalysisContext $context, Severity $severity): RuleResult
    {
        $violations = [];
        $ownership = $context->tableOwnership();

        foreach ($context->sourceIndex()->foreignKeyReferences() as $reference) {
            $sourceModule = $context->displayName($reference->source());
            $fromTable = $reference->fromTable();
            $toTable = $reference->toTable();

            if ($fromTable === null || $toTable === null) {
                $violations[] = new Violation(
                    $this->id(),
                    'MOD-FK-002',
                    Severity::Warning,
                    "Module [{$sourceModule}] defines unresolved foreign-key table evidence through [{$reference->operation()}].",
                    $reference->file(),
                    $reference->line(),
                    $sourceModule,
                    null,
                    $reference->evidence(),
                    'Use literal tables or an explicit constrained() table, then review the ownership boundary.',
                );

                continue;
            }

            $fromOwner = $ownership->owner($fromTable);
            $toOwner = $ownership->owner($toTable);

            if ($fromOwner === null || $toOwner === null) {
                $missing = [];

                if ($fromOwner === null) {
                    $missing[] = $fromTable;
                }

                if ($toOwner === null) {
                    $missing[] = $toTable;
                }

                $violations[] = new Violation(
                    $this->id(),
                    'MOD-FK-003',
                    $severity,
                    'Foreign key ['.$reference->evidence().'] cannot be classified because table ownership is missing for ['.implode(', ', $missing).'].',
                    $reference->file(),
                    $reference->line(),
                    $sourceModule,
                    null,
                    $reference->evidence(),
                    'Declare every referenced table in one authoritative Module tables() metadata list.',
                );

                continue;
            }

            if ($fromOwner === $toOwner) {
                continue;
            }

            $consumer = $context->displayName($fromOwner);
            $target = $context->displayName($toOwner);
            $violations[] = new Violation(
                $this->id(),
                'MOD-FK-001',
                $severity,
                "Table [{$fromTable}] owned by Module [{$consumer}] defines a foreign key to table [{$toTable}] owned by Module [{$target}].",
                $reference->file(),
                $reference->line(),
                $consumer,
                $target,
                $reference->evidence(),
                'Review the extraction coupling; keep it with a narrow suppression or validate the identifier through a Port or workflow.',
            );
        }

        return new RuleResult($this->id(), $violations);
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Rules;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\ArchitectureRule;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Architecture\Violation;

final class DatabaseOwnershipRule implements ArchitectureRule
{
    public function id(): RuleId
    {
        return RuleId::DatabaseOwnership;
    }

    public function inspect(AnalysisContext $context, Severity $severity): RuleResult
    {
        $violations = [];
        $ownership = $context->tableOwnership();

        foreach ($context->sourceIndex()->tableAccesses() as $access) {
            $consumer = $context->displayName($access->source());
            $table = $access->table();

            if ($table === null) {
                $violations[] = new Violation(
                    $this->id(),
                    'MOD-TABLE-003',
                    Severity::Warning,
                    "Module [{$consumer}] uses an unresolved table expression through [{$access->operation()}]; database ownership could not be verified.",
                    $access->file(),
                    $access->line(),
                    $consumer,
                    null,
                    $access->evidence(),
                    'Use a literal owned table name, or add a reviewed suppression until the query is statically analyzable.',
                );

                continue;
            }

            $owner = $ownership->owner($table);

            if ($owner === $access->source()) {
                continue;
            }

            if ($owner === null) {
                $violations[] = new Violation(
                    $this->id(),
                    'MOD-TABLE-002',
                    $severity,
                    "Module [{$consumer}] accesses table [{$table}], but no Module declares its ownership.",
                    $access->file(),
                    $access->line(),
                    $consumer,
                    null,
                    $table,
                    "Declare [{$table}] in the owning Module's tables() metadata, then access it through that boundary.",
                );

                continue;
            }

            $target = $context->displayName($owner);
            $violations[] = new Violation(
                $this->id(),
                'MOD-TABLE-001',
                $severity,
                "Module [{$consumer}] directly accesses table [{$table}] owned by Module [{$target}].",
                $access->file(),
                $access->line(),
                $consumer,
                $target,
                $table,
                "Query [{$table}] through Module [{$target}]'s Port or exported boundary instead.",
            );
        }

        return new RuleResult($this->id(), $violations);
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Rules;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\ArchitectureRule;
use Cluion\Moduark\Analysis\Source\SchemaMutation;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Architecture\Violation;

final class MigrationOwnershipRule implements ArchitectureRule
{
    public function id(): RuleId
    {
        return RuleId::MigrationOwnership;
    }

    public function inspect(AnalysisContext $context, Severity $severity): RuleResult
    {
        $violations = [];
        $outsideMigrationPaths = [];
        $ownership = $context->tableOwnership();

        foreach ($context->sourceIndex()->schemaMutations() as $mutation) {
            $consumer = $context->displayName($mutation->source());

            if (! $this->isMigrationFile($context, $mutation)) {
                $key = implode('|', [
                    $mutation->source(),
                    $mutation->file(),
                    (string) $mutation->line(),
                    $mutation->operation(),
                ]);

                if (! isset($outsideMigrationPaths[$key])) {
                    $violations[] = new Violation(
                        $this->id(),
                        'MOD-MIGRATION-003',
                        $severity,
                        "Module [{$consumer}] declares schema mutation [{$mutation->operation()}] outside [Database/Migrations/].",
                        $mutation->file(),
                        $mutation->line(),
                        $consumer,
                        null,
                        $mutation->operation(),
                        'Move schema mutations into the owning Module\'s Database/Migrations/ directory.',
                    );
                    $outsideMigrationPaths[$key] = true;
                }

                continue;
            }

            $table = $mutation->table();

            if ($table === null) {
                $violations[] = new Violation(
                    $this->id(),
                    'MOD-MIGRATION-004',
                    Severity::Warning,
                    "Module [{$consumer}] migration uses an unresolved table expression for [{$mutation->label()}]; migration ownership could not be verified.",
                    $mutation->file(),
                    $mutation->line(),
                    $consumer,
                    null,
                    $mutation->evidence(),
                    'Use a literal owned table name, or add a reviewed suppression until the migration is statically analyzable.',
                );

                continue;
            }

            $owner = $ownership->owner($table);

            if ($owner === $mutation->source()) {
                continue;
            }

            if ($owner === null) {
                $violations[] = new Violation(
                    $this->id(),
                    'MOD-MIGRATION-002',
                    $severity,
                    "Module [{$consumer}] migration [{$mutation->label()}] changes table [{$table}], but no Module declares its ownership.",
                    $mutation->file(),
                    $mutation->line(),
                    $consumer,
                    null,
                    $table,
                    "Declare [{$table}] in the owning Module's tables() metadata, including historical names used by shipped migrations.",
                );

                continue;
            }

            $target = $context->displayName($owner);
            $violations[] = new Violation(
                $this->id(),
                'MOD-MIGRATION-001',
                $severity,
                "Module [{$consumer}] migration [{$mutation->label()}] changes table [{$table}] owned by Module [{$target}].",
                $mutation->file(),
                $mutation->line(),
                $consumer,
                $target,
                $table,
                "Move this schema change to Module [{$target}] or record a reviewed orchestration suppression.",
            );
        }

        return new RuleResult($this->id(), $violations);
    }

    private function isMigrationFile(AnalysisContext $context, SchemaMutation $mutation): bool
    {
        $module = $context->module($mutation->source());

        if ($module === null) {
            return false;
        }

        $root = str_replace('\\', '/', dirname($module->path()));
        $file = str_replace('\\', '/', $mutation->file());

        return str_starts_with($file, rtrim($root, '/').'/Database/Migrations/');
    }
}

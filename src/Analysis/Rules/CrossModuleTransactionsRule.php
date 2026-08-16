<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Rules;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\ArchitectureRule;
use Cluion\Moduark\Analysis\Source\TransactionScope;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Architecture\Violation;
use Cluion\Moduark\Module;
use Cluion\Moduark\Persistence\TableName;

final class CrossModuleTransactionsRule implements ArchitectureRule
{
    public function id(): RuleId
    {
        return RuleId::CrossModuleTransactions;
    }

    public function inspect(AnalysisContext $context, Severity $severity): RuleResult
    {
        $violations = [];
        $ownership = $context->tableOwnership();

        foreach ($context->sourceIndex()->transactionScopes() as $scope) {
            $owners = [];
            $unowned = [];
            $unresolved = [];

            foreach ($scope->writes() as $write) {
                $table = $write->table();

                if ($table === null) {
                    $unresolved[strtolower($write->evidence())] ??= $write->evidence();

                    continue;
                }

                $owner = $ownership->owner($table);

                if ($owner === null) {
                    $unowned[TableName::key($table)] ??= $table;

                    continue;
                }

                $owners[$owner][TableName::key($table)] ??= $table;
            }

            if (count($owners) > 1) {
                $violations[] = $this->crossOwnerViolation(
                    $context,
                    $scope,
                    $owners,
                    $severity,
                );
            }

            if ($unresolved !== []) {
                ksort($unresolved, SORT_STRING);
                $source = $context->displayName($scope->source());
                $evidence = implode(', ', $unresolved);
                $violations[] = new Violation(
                    $this->id(),
                    'MOD-TRANSACTION-002',
                    Severity::Warning,
                    "Module [{$source}] has unresolved direct write targets inside transaction [{$scope->operation()}].",
                    $scope->file(),
                    $scope->line(),
                    $source,
                    null,
                    $evidence,
                    'Use a literal DB::table() or DB::query()->from() target, or review the unsupported write with a narrow suppression.',
                );
            }

            if ($unowned !== []) {
                ksort($unowned, SORT_STRING);
                $source = $context->displayName($scope->source());
                $evidence = implode(', ', $unowned);
                $violations[] = new Violation(
                    $this->id(),
                    'MOD-TRANSACTION-003',
                    $severity,
                    "Transaction [{$scope->operation()}] cannot be classified because table ownership is missing for [{$evidence}].",
                    $scope->file(),
                    $scope->line(),
                    $source,
                    null,
                    $evidence,
                    'Declare every directly mutated table in one authoritative Module tables() metadata list.',
                );
            }
        }

        return new RuleResult($this->id(), $violations);
    }

    /**
     * @param array<class-string<Module>, array<string, string>> $owners
     */
    private function crossOwnerViolation(
        AnalysisContext $context,
        TransactionScope $scope,
        array $owners,
        Severity $severity,
    ): Violation {
        $ownerNames = [];
        $tables = [];

        foreach ($owners as $owner => $ownedTables) {
            $ownerNames[] = $context->displayName($owner);

            foreach ($ownedTables as $key => $table) {
                $tables[$key] = $table;
            }
        }

        $this->sortLabels($ownerNames);
        ksort($tables, SORT_STRING);
        $source = $context->displayName($scope->source());
        $target = implode(', ', $ownerNames);
        $evidence = implode(', ', $tables);

        return new Violation(
            $this->id(),
            'MOD-TRANSACTION-001',
            $severity,
            "Transaction [{$scope->operation()}] directly mutates tables owned by multiple Modules [{$target}].",
            $scope->file(),
            $scope->line(),
            $source,
            $target,
            $evidence,
            'Move cross-owner orchestration behind Ports, or keep the atomic workflow with a narrow reviewed suppression.',
        );
    }

    /** @param list<string> $labels */
    private function sortLabels(array &$labels): void
    {
        usort($labels, static function (string $left, string $right): int {
            $folded = strcasecmp($left, $right);

            return $folded !== 0 ? $folded : strcmp($left, $right);
        });
    }
}

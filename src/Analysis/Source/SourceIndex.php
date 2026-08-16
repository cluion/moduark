<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Source;

use Cluion\Moduark\Exceptions\SourceAnalysisFailed;
use Cluion\Moduark\Module;

final readonly class SourceIndex
{
    private const ELOQUENT_MODEL = 'illuminate\\database\\eloquent\\model';

    /** @var list<SourceSymbol> */
    private array $symbols;

    /** @var array<string, SourceSymbol> */
    private array $symbolsByName;

    /** @var list<SourceReference> */
    private array $references;

    /** @var list<TableAccess> */
    private array $tableAccesses;

    /** @var list<SchemaMutation> */
    private array $schemaMutations;

    /** @var list<ForeignKeyReference> */
    private array $foreignKeyReferences;

    /** @var list<TransactionScope> */
    private array $transactionScopes;

    /**
     * @param list<SourceSymbol> $symbols
     * @param list<SourceReference> $references
     * @param list<TableAccess> $tableAccesses
     * @param list<SchemaMutation> $schemaMutations
     * @param list<ForeignKeyReference> $foreignKeyReferences
     * @param list<TransactionScope> $transactionScopes
     */
    public function __construct(
        array $symbols,
        array $references,
        array $tableAccesses = [],
        array $schemaMutations = [],
        array $foreignKeyReferences = [],
        array $transactionScopes = [],
    ) {
        $symbolsByName = [];

        foreach ($symbols as $symbol) {
            $key = strtolower($symbol->name());
            $existing = $symbolsByName[$key] ?? null;

            if ($existing !== null) {
                throw SourceAnalysisFailed::duplicateSymbol(
                    $symbol->name(),
                    $existing->file(),
                    $symbol->file(),
                );
            }

            $symbolsByName[$key] = $symbol;
        }

        foreach ($references as $reference) {
            $target = $symbolsByName[strtolower($reference->symbol())] ?? null;

            if ($target === null || $target->owner() !== $reference->target()) {
                throw SourceAnalysisFailed::invalidReference(
                    $reference->symbol(),
                    $reference->file(),
                    $reference->line(),
                );
            }
        }

        usort($symbols, static function (SourceSymbol $left, SourceSymbol $right): int {
            $byName = strcasecmp($left->name(), $right->name());

            if ($byName !== 0) {
                return $byName;
            }

            $byExactName = strcmp($left->name(), $right->name());

            return $byExactName !== 0
                ? $byExactName
                : [$left->file(), $left->line()] <=> [$right->file(), $right->line()];
        });

        usort($references, static function (SourceReference $left, SourceReference $right): int {
            return [
                $left->source(),
                $left->file(),
                $left->line(),
                strtolower($left->symbol()),
                $left->symbol(),
            ] <=> [
                $right->source(),
                $right->file(),
                $right->line(),
                strtolower($right->symbol()),
                $right->symbol(),
            ];
        });

        usort($tableAccesses, static function (TableAccess $left, TableAccess $right): int {
            return [
                $left->source(),
                $left->file(),
                $left->line(),
                strtolower($left->operation()),
                $left->evidence(),
            ] <=> [
                $right->source(),
                $right->file(),
                $right->line(),
                strtolower($right->operation()),
                $right->evidence(),
            ];
        });

        usort($schemaMutations, static function (SchemaMutation $left, SchemaMutation $right): int {
            return [
                $left->source(),
                $left->file(),
                $left->line(),
                strtolower($left->operation()),
                $left->operand(),
                $left->evidence(),
            ] <=> [
                $right->source(),
                $right->file(),
                $right->line(),
                strtolower($right->operation()),
                $right->operand(),
                $right->evidence(),
            ];
        });

        usort(
            $foreignKeyReferences,
            static function (ForeignKeyReference $left, ForeignKeyReference $right): int {
                return [
                    $left->source(),
                    $left->file(),
                    $left->line(),
                    strtolower($left->operation()),
                    $left->evidence(),
                ] <=> [
                    $right->source(),
                    $right->file(),
                    $right->line(),
                    strtolower($right->operation()),
                    $right->evidence(),
                ];
            },
        );

        usort(
            $transactionScopes,
            static function (TransactionScope $left, TransactionScope $right): int {
                return [
                    $left->source(),
                    $left->file(),
                    $left->line(),
                    strtolower($left->operation()),
                    $left->evidence(),
                ] <=> [
                    $right->source(),
                    $right->file(),
                    $right->line(),
                    strtolower($right->operation()),
                    $right->evidence(),
                ];
            },
        );

        $this->symbols = $symbols;
        $this->symbolsByName = $symbolsByName;
        $this->references = $references;
        $this->tableAccesses = $tableAccesses;
        $this->schemaMutations = $schemaMutations;
        $this->foreignKeyReferences = $foreignKeyReferences;
        $this->transactionScopes = $transactionScopes;
    }

    /**
     * @return list<SourceSymbol>
     */
    public function symbols(): array
    {
        return $this->symbols;
    }

    public function symbol(string $name): ?SourceSymbol
    {
        return $this->symbolsByName[strtolower(ltrim($name, '\\'))] ?? null;
    }

    public function isEloquentModel(string|SourceSymbol $symbol): bool
    {
        $candidate = is_string($symbol) ? $this->symbol($symbol) : $symbol;
        $visited = [];

        while ($candidate !== null) {
            $key = strtolower(ltrim($candidate->name(), '\\'));

            if (isset($visited[$key])) {
                return false;
            }

            $visited[$key] = true;
            $parent = $candidate->parent();

            if ($parent === null) {
                return false;
            }

            $normalizedParent = strtolower(ltrim($parent, '\\'));

            if ($normalizedParent === self::ELOQUENT_MODEL) {
                return true;
            }

            $candidate = $this->symbolsByName[$normalizedParent] ?? null;
        }

        return false;
    }

    /**
     * @return list<SourceReference>
     */
    public function references(): array
    {
        return $this->references;
    }

    /**
     * @param class-string<Module> $moduleClass
     * @return list<SourceReference>
     */
    public function referencesFrom(string $moduleClass): array
    {
        return array_values(array_filter(
            $this->references,
            static fn (SourceReference $reference): bool => $reference->source() === $moduleClass,
        ));
    }

    /**
     * @return list<TableAccess>
     */
    public function tableAccesses(): array
    {
        return $this->tableAccesses;
    }

    /**
     * @param class-string<Module> $moduleClass
     * @return list<TableAccess>
     */
    public function tableAccessesFrom(string $moduleClass): array
    {
        return array_values(array_filter(
            $this->tableAccesses,
            static fn (TableAccess $access): bool => $access->source() === $moduleClass,
        ));
    }

    /**
     * @return list<SchemaMutation>
     */
    public function schemaMutations(): array
    {
        return $this->schemaMutations;
    }

    /**
     * @param class-string<Module> $moduleClass
     * @return list<SchemaMutation>
     */
    public function schemaMutationsFrom(string $moduleClass): array
    {
        return array_values(array_filter(
            $this->schemaMutations,
            static fn (SchemaMutation $mutation): bool => $mutation->source() === $moduleClass,
        ));
    }

    /**
     * @return list<ForeignKeyReference>
     */
    public function foreignKeyReferences(): array
    {
        return $this->foreignKeyReferences;
    }

    /**
     * @param class-string<Module> $moduleClass
     * @return list<ForeignKeyReference>
     */
    public function foreignKeyReferencesFrom(string $moduleClass): array
    {
        return array_values(array_filter(
            $this->foreignKeyReferences,
            static fn (ForeignKeyReference $reference): bool => $reference->source() === $moduleClass,
        ));
    }

    /** @return list<TransactionScope> */
    public function transactionScopes(): array
    {
        return $this->transactionScopes;
    }

    /**
     * @param class-string<Module> $moduleClass
     * @return list<TransactionScope>
     */
    public function transactionScopesFrom(string $moduleClass): array
    {
        return array_values(array_filter(
            $this->transactionScopes,
            static fn (TransactionScope $scope): bool => $scope->source() === $moduleClass,
        ));
    }
}

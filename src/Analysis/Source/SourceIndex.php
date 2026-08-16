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

    /**
     * @param list<SourceSymbol> $symbols
     * @param list<SourceReference> $references
     */
    public function __construct(array $symbols, array $references)
    {
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

        $this->symbols = $symbols;
        $this->symbolsByName = $symbolsByName;
        $this->references = $references;
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
}

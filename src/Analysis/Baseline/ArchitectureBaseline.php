<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Baseline;

use Cluion\Moduark\Analysis\CheckReport;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Violation;
use InvalidArgumentException;

final readonly class ArchitectureBaseline
{
    public const SCHEMA_VERSION = 1;

    /**
     * @param list<BaselineEntry> $entries
     */
    public function __construct(
        private int $generatedLevel,
        private array $entries,
    ) {
        if ($generatedLevel < 0 || $generatedLevel > 3) {
            throw new InvalidArgumentException('Baseline generated_level must be an integer from 0 to 3.');
        }

        $identities = [];

        foreach ($entries as $entry) {
            $identity = $entry->identity();

            if (isset($identities[$identity])) {
                throw new InvalidArgumentException('Baseline violation identities must be unique.');
            }

            $identities[$identity] = true;
        }
    }

    public static function fromReport(CheckReport $report, string $basePath): self
    {
        $entries = self::groupViolations($report->violations(), $basePath);

        return new self($report->architecture()->level()->value, array_values($entries));
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        if (($values['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Baseline schema_version must be 1.');
        }

        $level = $values['generated_level'] ?? null;
        $violations = $values['violations'] ?? null;

        if (! is_int($level)) {
            throw new InvalidArgumentException('Baseline generated_level must be an integer from 0 to 3.');
        }

        if (! is_array($violations) || ! array_is_list($violations)) {
            throw new InvalidArgumentException('Baseline violations must be a list.');
        }

        $entries = [];

        foreach ($violations as $violation) {
            if (! is_array($violation)) {
                throw new InvalidArgumentException('Each baseline violation must be an object.');
            }

            /** @var array<string, mixed> $violation */
            $entries[] = BaselineEntry::fromArray($violation);
        }

        return new self($level, $entries);
    }

    public function apply(CheckReport $report, string $path, string $basePath): CheckReport
    {
        $current = self::groupViolations($report->violations(), $basePath);
        $baseline = $this->indexedEntries();
        $evaluatedRules = $this->evaluatedRules($report);
        $suppressed = [];
        $matched = 0;
        $stale = 0;
        $exceeded = 0;

        foreach ($current as $identity => $entry) {
            $baselineEntry = $baseline[$identity] ?? null;
            $allowance = $baselineEntry?->count() ?? 0;

            if ($allowance >= $entry->count() && $allowance > 0) {
                $suppressed[$identity] = true;
                $matched += $entry->count();
                $stale += $allowance - $entry->count();
            } elseif ($allowance > 0) {
                $exceeded += $entry->count();
            }
        }

        foreach ($baseline as $identity => $entry) {
            if (isset($current[$identity]) || ! isset($evaluatedRules[$entry->rule()->value])) {
                continue;
            }

            $stale += $entry->count();
        }

        $results = array_map(
            function (RuleResult $result) use ($suppressed, $basePath): RuleResult {
                $violations = array_values(array_filter(
                    $result->violations(),
                    static fn (Violation $violation): bool => ! isset(
                        $suppressed[BaselineEntry::fromViolation($violation, $basePath)->identity()],
                    ),
                ));

                return new RuleResult($result->rule(), $violations);
            },
            $report->results(),
        );

        return new CheckReport(
            $report->architecture(),
            $results,
            $report->unavailableRules(),
            new BaselineStatus(
                PortablePath::relative($path, $basePath),
                $this->violationCount(),
                $matched,
                $stale,
                $exceeded,
            ),
        );
    }

    public function prune(CheckReport $report, string $basePath): self
    {
        $current = self::groupViolations($report->violations(), $basePath);
        $evaluatedRules = $this->evaluatedRules($report);
        $entries = [];

        foreach ($this->entries as $entry) {
            if (! isset($evaluatedRules[$entry->rule()->value])) {
                $entries[] = $entry;

                continue;
            }

            $currentEntry = $current[$entry->identity()] ?? null;
            $count = min($entry->count(), $currentEntry?->count() ?? 0);

            if ($count > 0) {
                $entries[] = $entry->withCount($count);
            }
        }

        return new self($this->generatedLevel, $entries);
    }

    public function violationCount(): int
    {
        return array_sum(array_map(
            static fn (BaselineEntry $entry): int => $entry->count(),
            $this->entries,
        ));
    }

    /**
     * @return array{schema_version: int, generated_level: int, violations: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'generated_level' => $this->generatedLevel,
            'violations' => array_map(
                static fn (BaselineEntry $entry): array => $entry->toArray(),
                $this->entries,
            ),
        ];
    }

    /**
     * @param list<Violation> $violations
     * @return array<string, BaselineEntry>
     */
    private static function groupViolations(array $violations, string $basePath): array
    {
        $entries = [];

        foreach ($violations as $violation) {
            $entry = BaselineEntry::fromViolation($violation, $basePath);
            $identity = $entry->identity();
            $entries[$identity] = isset($entries[$identity])
                ? $entries[$identity]->withCount($entries[$identity]->count() + 1)
                : $entry;
        }

        ksort($entries, SORT_STRING);

        return $entries;
    }

    /**
     * @return array<string, BaselineEntry>
     */
    private function indexedEntries(): array
    {
        $entries = [];

        foreach ($this->entries as $entry) {
            $entries[$entry->identity()] = $entry;
        }

        return $entries;
    }

    /**
     * @return array<string, true>
     */
    private function evaluatedRules(CheckReport $report): array
    {
        $rules = [];

        foreach ($report->results() as $result) {
            $rules[$result->rule()->value] = true;
        }

        return $rules;
    }
}

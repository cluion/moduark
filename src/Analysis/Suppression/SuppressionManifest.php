<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Suppression;

use Cluion\Moduark\Analysis\Baseline\PortablePath;
use Cluion\Moduark\Analysis\CheckReport;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Violation;
use InvalidArgumentException;

final readonly class SuppressionManifest
{
    public const SCHEMA_VERSION = 1;

    /**
     * @param list<SuppressionEntry> $entries
     */
    public function __construct(private array $entries)
    {
        $identities = [];

        foreach ($entries as $entry) {
            $identity = $entry->identity();

            if (isset($identities[$identity])) {
                throw new InvalidArgumentException('Suppression selectors must be unique.');
            }

            $identities[$identity] = true;
        }
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        $unknown = array_values(array_diff(array_keys($values), ['schema_version', 'suppressions']));

        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf(
                'Suppression manifest contains unknown field%s: %s.',
                count($unknown) === 1 ? '' : 's',
                implode(', ', $unknown),
            ));
        }

        if (($values['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Suppression schema_version must be 1.');
        }

        $values = $values['suppressions'] ?? null;

        if (! is_array($values) || ! array_is_list($values)) {
            throw new InvalidArgumentException('Suppression manifest suppressions must be a list.');
        }

        $entries = [];

        foreach ($values as $value) {
            if (! is_array($value)) {
                throw new InvalidArgumentException('Each suppression must be an object.');
            }

            /** @var array<string, mixed> $value */
            $entries[] = SuppressionEntry::fromArray($value);
        }

        return new self($entries);
    }

    public function apply(CheckReport $report, string $path, string $basePath): CheckReport
    {
        $matchCounts = array_fill(0, count($this->entries), 0);
        $matched = 0;
        $results = array_map(
            function (RuleResult $result) use (&$matchCounts, &$matched, $basePath): RuleResult {
                $violations = array_values(array_filter(
                    $result->violations(),
                    function (Violation $violation) use (&$matchCounts, &$matched, $basePath): bool {
                        $matches = [];

                        foreach ($this->entries as $index => $entry) {
                            if ($entry->matches($violation, $basePath)) {
                                $matches[] = $index;
                            }
                        }

                        if (count($matches) > 1) {
                            throw new InvalidArgumentException(sprintf(
                                'Violation [%s %s] at [%s] matches overlapping suppressions.',
                                $violation->rule()->value,
                                $violation->code(),
                                $this->location($violation, $basePath),
                            ));
                        }

                        if ($matches === []) {
                            return true;
                        }

                        $matchCounts[$matches[0]]++;
                        $matched++;

                        return false;
                    },
                ));

                return new RuleResult($result->rule(), $violations);
            },
            $report->results(),
        );
        $evaluated = [];

        foreach ($report->results() as $result) {
            $evaluated[$result->rule()->value] = true;
        }

        $details = array_map(
            static function (SuppressionEntry $entry, int $index) use ($matchCounts, $evaluated): SuppressionAudit {
                $active = isset($evaluated[$entry->rule()->value]);
                $status = ! $active ? 'inactive' : ($matchCounts[$index] === 0 ? 'stale' : 'matched');

                return new SuppressionAudit($entry, $status, $matchCounts[$index]);
            },
            $this->entries,
            array_keys($this->entries),
        );

        return new CheckReport(
            $report->architecture(),
            $results,
            $report->unavailableRules(),
            $report->baseline(),
            new SuppressionStatus(PortablePath::relative($path, $basePath), $matched, $details),
        );
    }

    private function location(Violation $violation, string $basePath): string
    {
        $location = $violation->file() === null
            ? ($violation->symbol() ?? 'unknown')
            : PortablePath::relative($violation->file(), $basePath);

        return $violation->line() === null ? $location : $location.':'.$violation->line();
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Suppression;

final readonly class SuppressionStatus
{
    /**
     * @param list<SuppressionAudit> $details
     */
    public function __construct(
        private string $path,
        private int $matched,
        private array $details,
    ) {
    }

    public function path(): string
    {
        return $this->path;
    }

    public function entries(): int
    {
        return count($this->details);
    }

    public function matched(): int
    {
        return $this->matched;
    }

    public function stale(): int
    {
        return count(array_filter(
            $this->details,
            static fn (SuppressionAudit $audit): bool => $audit->status() === 'stale',
        ));
    }

    public function inactive(): int
    {
        return count(array_filter(
            $this->details,
            static fn (SuppressionAudit $audit): bool => $audit->status() === 'inactive',
        ));
    }

    /**
     * @return list<SuppressionAudit>
     */
    public function details(): array
    {
        return $this->details;
    }

    /**
     * @return array{path: string, entries: int, matched: int, stale: int, inactive: int, details: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'entries' => $this->entries(),
            'matched' => $this->matched,
            'stale' => $this->stale(),
            'inactive' => $this->inactive(),
            'details' => array_map(
                static fn (SuppressionAudit $audit): array => $audit->toArray(),
                $this->details,
            ),
        ];
    }
}

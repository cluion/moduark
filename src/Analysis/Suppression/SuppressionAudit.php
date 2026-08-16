<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Suppression;

final readonly class SuppressionAudit
{
    public function __construct(
        private SuppressionEntry $entry,
        private string $status,
        private int $matches,
    ) {
    }

    public function entry(): SuppressionEntry
    {
        return $this->entry;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function matches(): int
    {
        return $this->matches;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->entry->toArray() + [
            'status' => $this->status,
            'matches' => $this->matches,
        ];
    }
}

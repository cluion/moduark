<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Baseline;

final readonly class BaselineStatus
{
    public function __construct(
        private string $path,
        private int $violations,
        private int $matched,
        private int $stale,
        private int $exceeded,
    ) {
    }

    public function path(): string
    {
        return $this->path;
    }

    public function violations(): int
    {
        return $this->violations;
    }

    public function matched(): int
    {
        return $this->matched;
    }

    public function stale(): int
    {
        return $this->stale;
    }

    public function exceeded(): int
    {
        return $this->exceeded;
    }

    /**
     * @return array{path: string, violations: int, matched: int, stale: int, exceeded: int}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'violations' => $this->violations,
            'matched' => $this->matched,
            'stale' => $this->stale,
            'exceeded' => $this->exceeded,
        ];
    }
}

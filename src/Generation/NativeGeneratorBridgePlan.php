<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

final readonly class NativeGeneratorBridgePlan
{
    /** @param list<NativeGeneratorBridgeCandidate> $candidates */
    public function __construct(
        private bool $optedIn,
        private array $candidates,
    ) {
    }

    public function optedIn(): bool
    {
        return $this->optedIn;
    }

    /** @return list<NativeGeneratorBridgeCandidate> */
    public function candidates(): array
    {
        return $this->candidates;
    }

    public function readyCount(): int
    {
        return count(array_filter(
            $this->candidates,
            static fn (NativeGeneratorBridgeCandidate $candidate): bool => $candidate->ready(),
        ));
    }

    public function blockedCount(): int
    {
        return count($this->candidates) - $this->readyCount();
    }

    public function activeCount(): int
    {
        return count(array_filter(
            $this->candidates,
            static fn (NativeGeneratorBridgeCandidate $candidate): bool => $candidate->decorated(),
        ));
    }

    public function ready(): bool
    {
        return $this->blockedCount() === 0;
    }

    /** @return 'disabled'|'planned'|'active'|'blocked' */
    public function status(): string
    {
        if (! $this->optedIn) {
            return 'disabled';
        }

        if (! $this->ready()) {
            return 'blocked';
        }

        return $this->activeCount() === count($this->candidates) ? 'active' : 'planned';
    }

    public function exitCode(): int
    {
        return $this->optedIn && ! $this->ready() ? 1 : 0;
    }
}

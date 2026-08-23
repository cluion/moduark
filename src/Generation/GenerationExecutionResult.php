<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

final readonly class GenerationExecutionResult
{
    /** @param list<string> $rollbackFailures */
    private function __construct(
        private int $exitCode,
        private ?string $failure,
        private bool $rollbackAttempted,
        private array $rollbackFailures,
    ) {
    }

    public static function success(): self
    {
        return new self(0, null, false, []);
    }

    /** @param list<string> $rollbackFailures */
    public static function failure(
        int $exitCode,
        ?string $failure,
        array $rollbackFailures,
        bool $rollbackAttempted = true,
    ): self {
        return new self($exitCode, $failure, $rollbackAttempted, $rollbackFailures);
    }

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }

    public function exitCode(): int
    {
        return $this->exitCode;
    }

    public function failureMessage(): ?string
    {
        return $this->failure;
    }

    public function rollbackAttempted(): bool
    {
        return $this->rollbackAttempted;
    }

    /** @return list<string> */
    public function rollbackFailures(): array
    {
        return $this->rollbackFailures;
    }
}

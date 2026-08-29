<?php

declare(strict_types=1);

namespace Cluion\Moduark\Export;

final readonly class ModuleExportExecutionResult
{
    /** @var list<string> */
    private array $rollbackFailures;

    /** @param list<string> $rollbackFailures */
    private function __construct(
        private bool $successful,
        private ?string $error,
        array $rollbackFailures,
    ) {
        sort($rollbackFailures, SORT_STRING);
        $this->rollbackFailures = array_values(array_unique($rollbackFailures));
    }

    public static function success(): self
    {
        return new self(true, null, []);
    }

    /** @param list<string> $rollbackFailures */
    public static function failure(string $error, array $rollbackFailures = []): self
    {
        return new self(false, $error, $rollbackFailures);
    }

    public function successful(): bool
    {
        return $this->successful;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    /** @return list<string> */
    public function rollbackFailures(): array
    {
        return $this->rollbackFailures;
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Export;

final readonly class ModuleExportSetExecutionResult
{
    /** @var list<string> */
    private array $publishedTargets;

    /** @var list<string> */
    private array $publishedBeforeRollback;

    /** @var list<string> */
    private array $remainingTargets;

    /** @var list<string> */
    private array $rollbackFailures;

    /**
     * @param list<string> $publishedTargets
     * @param list<string> $publishedBeforeRollback
     * @param list<string> $remainingTargets
     * @param list<string> $rollbackFailures
     */
    private function __construct(
        private bool $successful,
        private ?string $error,
        array $publishedTargets,
        array $publishedBeforeRollback,
        array $remainingTargets,
        array $rollbackFailures,
    ) {
        sort($rollbackFailures, SORT_STRING);
        $this->publishedTargets = $publishedTargets;
        $this->publishedBeforeRollback = $publishedBeforeRollback;
        $this->remainingTargets = $remainingTargets;
        $this->rollbackFailures = array_values(array_unique($rollbackFailures));
    }

    /** @param list<string> $publishedTargets */
    public static function success(array $publishedTargets): self
    {
        return new self(true, null, $publishedTargets, [], [], []);
    }

    /**
     * @param list<string> $publishedBeforeRollback
     * @param list<string> $remainingTargets
     * @param list<string> $rollbackFailures
     */
    public static function failure(
        string $error,
        array $publishedBeforeRollback = [],
        array $remainingTargets = [],
        array $rollbackFailures = [],
    ): self {
        return new self(
            false,
            $error,
            [],
            $publishedBeforeRollback,
            $remainingTargets,
            $rollbackFailures,
        );
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
    public function publishedTargets(): array
    {
        return $this->publishedTargets;
    }

    /** @return list<string> */
    public function publishedBeforeRollback(): array
    {
        return $this->publishedBeforeRollback;
    }

    /** @return list<string> */
    public function remainingTargets(): array
    {
        return $this->remainingTargets;
    }

    /** @return list<string> */
    public function rollbackFailures(): array
    {
        return $this->rollbackFailures;
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Source;

use Cluion\Moduark\Module;
use Cluion\Moduark\Persistence\TableName;
use InvalidArgumentException;

final readonly class ForeignKeyReference
{
    /** @var class-string<Module> */
    private string $source;

    public function __construct(
        string $source,
        private ?string $fromTable,
        private ?string $fromExpression,
        private ?string $toTable,
        private ?string $toExpression,
        private string $operation,
        private string $file,
        private int $line,
    ) {
        if (! is_a($source, Module::class, true)) {
            throw new InvalidArgumentException('A foreign-key source must extend Module.');
        }

        $this->validateTableEvidence($this->fromTable, $this->fromExpression, 'source');
        $this->validateTableEvidence($this->toTable, $this->toExpression, 'target');

        if (trim($this->operation) === '') {
            throw new InvalidArgumentException('A foreign-key operation must not be empty.');
        }

        if (trim($this->file) === '' || $this->line < 1) {
            throw new InvalidArgumentException('A foreign-key reference must have a file and positive line.');
        }

        $this->source = $source;
    }

    /**
     * @return class-string<Module>
     */
    public function source(): string
    {
        return $this->source;
    }

    public function fromTable(): ?string
    {
        return $this->fromTable;
    }

    public function fromExpression(): ?string
    {
        return $this->fromExpression;
    }

    public function toTable(): ?string
    {
        return $this->toTable;
    }

    public function toExpression(): ?string
    {
        return $this->toExpression;
    }

    public function operation(): string
    {
        return $this->operation;
    }

    public function file(): string
    {
        return $this->file;
    }

    public function line(): int
    {
        return $this->line;
    }

    public function resolved(): bool
    {
        return $this->fromTable !== null && $this->toTable !== null;
    }

    public function evidence(): string
    {
        return $this->fromEvidence().' -> '.$this->toEvidence();
    }

    /**
     * @return array{
     *     source: class-string<Module>,
     *     from_table: ?string,
     *     from_expression: ?string,
     *     to_table: ?string,
     *     to_expression: ?string,
     *     operation: string,
     *     file: string,
     *     line: int
     * }
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'from_table' => $this->fromTable,
            'from_expression' => $this->fromExpression,
            'to_table' => $this->toTable,
            'to_expression' => $this->toExpression,
            'operation' => $this->operation,
            'file' => $this->file,
            'line' => $this->line,
        ];
    }

    private function fromEvidence(): string
    {
        return $this->fromTable
            ?? $this->fromExpression
            ?? $this->operation.'(from:*)';
    }

    private function toEvidence(): string
    {
        return $this->toTable
            ?? $this->toExpression
            ?? $this->operation.'(to:*)';
    }

    private function validateTableEvidence(?string $table, ?string $expression, string $side): void
    {
        if ($table !== null && ! TableName::valid($table)) {
            throw new InvalidArgumentException("A resolved foreign-key {$side} must have a canonical table name.");
        }

        if ($table !== null && $expression !== null) {
            throw new InvalidArgumentException("A resolved foreign-key {$side} must not retain an expression.");
        }

        if ($expression !== null && trim($expression) === '') {
            throw new InvalidArgumentException("A foreign-key {$side} expression must not be empty.");
        }
    }
}

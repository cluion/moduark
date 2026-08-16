<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Source;

use Cluion\Moduark\Persistence\TableName;
use InvalidArgumentException;

final readonly class TransactionWrite
{
    public function __construct(
        private ?string $table,
        private ?string $expression,
        private string $operation,
        private int $line,
    ) {
        if ($this->table !== null && ! TableName::valid($this->table)) {
            throw new InvalidArgumentException('A resolved transaction write must have a canonical table name.');
        }

        if ($this->table !== null && $this->expression !== null) {
            throw new InvalidArgumentException('A resolved transaction write must not retain an expression.');
        }

        if ($this->expression !== null && trim($this->expression) === '') {
            throw new InvalidArgumentException('A transaction write expression must not be empty.');
        }

        if (trim($this->operation) === '' || $this->line < 1) {
            throw new InvalidArgumentException('A transaction write must have an operation and positive line.');
        }
    }

    public function table(): ?string
    {
        return $this->table;
    }

    public function expression(): ?string
    {
        return $this->expression;
    }

    public function operation(): string
    {
        return $this->operation;
    }

    public function line(): int
    {
        return $this->line;
    }

    public function evidence(): string
    {
        return $this->table
            ?? $this->expression
            ?? $this->operation.'(table:*)';
    }

    /**
     * @return array{
     *     table: ?string,
     *     expression: ?string,
     *     operation: string,
     *     line: int
     * }
     */
    public function toArray(): array
    {
        return [
            'table' => $this->table,
            'expression' => $this->expression,
            'operation' => $this->operation,
            'line' => $this->line,
        ];
    }
}

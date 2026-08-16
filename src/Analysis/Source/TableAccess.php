<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Source;

use Cluion\Moduark\Module;
use Cluion\Moduark\Persistence\TableName;
use InvalidArgumentException;

final readonly class TableAccess
{
    /** @var class-string<Module> */
    private string $source;

    public function __construct(
        string $source,
        private ?string $table,
        private ?string $expression,
        private string $operation,
        private string $file,
        private int $line,
    ) {
        if (! is_a($source, Module::class, true)) {
            throw new InvalidArgumentException('A table access owner must extend Module.');
        }

        if ($this->table !== null && ! TableName::valid($this->table)) {
            throw new InvalidArgumentException('A resolved table access must have a canonical table name.');
        }

        if ($this->table !== null && $this->expression !== null) {
            throw new InvalidArgumentException('A resolved table access must not retain an unresolved expression.');
        }

        if ($this->expression !== null && trim($this->expression) === '') {
            throw new InvalidArgumentException('A table access expression must not be empty.');
        }

        if (trim($this->operation) === '') {
            throw new InvalidArgumentException('A table access operation must not be empty.');
        }

        if (trim($this->file) === '' || $this->line < 1) {
            throw new InvalidArgumentException('A table access must have a file and positive line.');
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
        return $this->table !== null;
    }

    public function evidence(): string
    {
        if ($this->table !== null) {
            return $this->table;
        }

        if ($this->expression !== null) {
            return $this->expression;
        }

        return $this->operation.'(*)';
    }

    /**
     * @return array{
     *     source: class-string<Module>,
     *     table: ?string,
     *     expression: ?string,
     *     operation: string,
     *     file: string,
     *     line: int
     * }
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'table' => $this->table,
            'expression' => $this->expression,
            'operation' => $this->operation,
            'file' => $this->file,
            'line' => $this->line,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Source;

use Cluion\Moduark\Module;
use InvalidArgumentException;

final readonly class TransactionScope
{
    /** @var class-string<Module> */
    private string $source;

    /** @var list<TransactionWrite> */
    private array $writes;

    /**
     * @param list<TransactionWrite> $writes
     */
    public function __construct(
        string $source,
        private string $operation,
        array $writes,
        private string $file,
        private int $line,
    ) {
        if (! is_a($source, Module::class, true)) {
            throw new InvalidArgumentException('A transaction source must extend Module.');
        }

        if (trim($this->operation) === '' || $writes === []) {
            throw new InvalidArgumentException('A transaction scope must have an operation and direct writes.');
        }

        if (trim($this->file) === '' || $this->line < 1) {
            throw new InvalidArgumentException('A transaction scope must have a file and positive line.');
        }

        usort($writes, static function (TransactionWrite $left, TransactionWrite $right): int {
            return [
                $left->line(),
                strtolower($left->operation()),
                $left->evidence(),
            ] <=> [
                $right->line(),
                strtolower($right->operation()),
                $right->evidence(),
            ];
        });

        $this->source = $source;
        $this->writes = $writes;
    }

    /** @return class-string<Module> */
    public function source(): string
    {
        return $this->source;
    }

    public function operation(): string
    {
        return $this->operation;
    }

    /** @return list<TransactionWrite> */
    public function writes(): array
    {
        return $this->writes;
    }

    public function file(): string
    {
        return $this->file;
    }

    public function line(): int
    {
        return $this->line;
    }

    public function evidence(): string
    {
        $evidence = [];

        foreach ($this->writes as $write) {
            $evidence[strtolower($write->evidence())] = $write->evidence();
        }

        ksort($evidence, SORT_STRING);

        return implode(', ', $evidence);
    }

    /**
     * @return array{
     *     source: class-string<Module>,
     *     operation: string,
     *     writes: list<array{table: ?string, expression: ?string, operation: string, line: int}>,
     *     file: string,
     *     line: int
     * }
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'operation' => $this->operation,
            'writes' => array_map(
                static fn (TransactionWrite $write): array => $write->toArray(),
                $this->writes,
            ),
            'file' => $this->file,
            'line' => $this->line,
        ];
    }
}

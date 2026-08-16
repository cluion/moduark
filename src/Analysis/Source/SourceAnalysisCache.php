<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Source;

use Cluion\Moduark\Module;
use InvalidArgumentException;

final readonly class SourceAnalysisCache
{
    public const SCHEMA_VERSION = 6;

    /** @var array<string, SourceFileAnalysis> */
    private array $files;

    /**
     * @param list<SourceFileAnalysis> $files
     */
    public function __construct(array $files)
    {
        $byPath = [];

        foreach ($files as $file) {
            if (isset($byPath[$file->file()])) {
                throw new InvalidArgumentException('The source analysis cache contains duplicate files.');
            }

            $byPath[$file->file()] = $file;
        }

        ksort($byPath, SORT_STRING);

        $this->files = $byPath;
    }

    /**
     * @param array<mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        if (($payload['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('The source analysis cache schema is invalid.');
        }

        $rows = $payload['files'] ?? null;

        if (! is_array($rows)) {
            throw new InvalidArgumentException('The source analysis cache payload is invalid.');
        }

        $files = [];

        foreach ($rows as $path => $row) {
            if (! is_string($path) || $path === '' || ! is_array($row)) {
                throw new InvalidArgumentException('The source analysis cache files are invalid.');
            }

            $files[] = SourceFileAnalysis::fromArray($path, $row);
        }

        return new self($files);
    }

    /**
     * @param class-string<Module> $owner
     */
    public function match(string $file, string $hash, string $owner): ?SourceFileAnalysis
    {
        $analysis = $this->files[$file] ?? null;

        return $analysis?->matches($hash, $owner) === true ? $analysis : null;
    }

    /**
     * @return array{
     *     schema_version: int,
     *     files: array<string, array{
     *         hash: string,
     *         owner: class-string<Module>,
     *         symbols: list<array{name: string, line: int, parent: ?string}>,
     *         references: list<array{symbol: string, line: int}>,
     *         table_accesses?: list<array{table: ?string, expression: ?string, operation: string, line: int}>,
     *         schema_mutations?: list<array{table: ?string, expression: ?string, operation: string, operand: string, line: int}>,
     *         foreign_keys?: list<array{from_table: ?string, from_expression: ?string, to_table: ?string, to_expression: ?string, operation: string, line: int}>,
     *         transaction_scopes?: list<array{operation: string, writes: list<array{table: ?string, expression: ?string, operation: string, line: int}>, line: int}>
     *     }>
     * }
     */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'files' => array_map(
                static fn (SourceFileAnalysis $file): array => $file->toArray(),
                $this->files,
            ),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Source;

use Cluion\Moduark\Module;
use InvalidArgumentException;

final readonly class SourceFileAnalysis
{
    /** @var class-string<Module> */
    private string $owner;

    /**
     * @param list<SourceSymbol> $symbols
     * @param list<array{symbol: string, line: int}> $references
     */
    public function __construct(
        private string $hash,
        string $owner,
        private string $file,
        private array $symbols,
        private array $references,
    ) {
        if (preg_match('/\A[a-f0-9]{64}\z/D', $this->hash) !== 1) {
            throw new InvalidArgumentException('A source analysis hash must be SHA-256.');
        }

        if (! is_a($owner, Module::class, true)) {
            throw new InvalidArgumentException('A source analysis owner must extend Module.');
        }

        if (trim($this->file) === '') {
            throw new InvalidArgumentException('A source analysis file must not be empty.');
        }

        foreach ($this->symbols as $symbol) {
            if ($symbol->owner() !== $owner || $symbol->file() !== $this->file) {
                throw new InvalidArgumentException('Cached source symbols must match their file and owner.');
            }
        }

        foreach ($this->references as $reference) {
            if (trim($reference['symbol']) === '' || $reference['line'] < 1) {
                throw new InvalidArgumentException('Cached source references must have a symbol and positive line.');
            }
        }

        $this->owner = $owner;
    }

    /**
     * @param array<mixed> $payload
     */
    public static function fromArray(string $file, array $payload): self
    {
        $hash = $payload['hash'] ?? null;
        $owner = $payload['owner'] ?? null;
        $symbolRows = $payload['symbols'] ?? null;
        $referenceRows = $payload['references'] ?? null;

        if (! is_string($hash) || ! is_string($owner)
            || ! is_array($symbolRows) || ! array_is_list($symbolRows)
            || ! is_array($referenceRows) || ! array_is_list($referenceRows)) {
            throw new InvalidArgumentException('The source analysis cache entry is invalid.');
        }

        $symbols = [];

        foreach ($symbolRows as $row) {
            if (! is_array($row)
                || ! is_string($row['name'] ?? null)
                || ! is_int($row['line'] ?? null)) {
                throw new InvalidArgumentException('The cached source symbols are invalid.');
            }

            $symbols[] = new SourceSymbol($row['name'], $owner, $file, $row['line']);
        }

        $references = [];

        foreach ($referenceRows as $row) {
            if (! is_array($row)
                || ! is_string($row['symbol'] ?? null)
                || ! is_int($row['line'] ?? null)) {
                throw new InvalidArgumentException('The cached source references are invalid.');
            }

            $references[] = [
                'symbol' => $row['symbol'],
                'line' => $row['line'],
            ];
        }

        /** @var class-string<Module> $owner */
        return new self($hash, $owner, $file, $symbols, $references);
    }

    public function matches(string $hash, string $owner): bool
    {
        return hash_equals($this->hash, $hash) && $this->owner === $owner;
    }

    public function file(): string
    {
        return $this->file;
    }

    /**
     * @return list<SourceSymbol>
     */
    public function symbols(): array
    {
        return $this->symbols;
    }

    /**
     * @return list<array{symbol: string, line: int}>
     */
    public function references(): array
    {
        return $this->references;
    }

    /**
     * @return array{
     *     hash: string,
     *     owner: class-string<Module>,
     *     symbols: list<array{name: string, line: int}>,
     *     references: list<array{symbol: string, line: int}>
     * }
     */
    public function toArray(): array
    {
        return [
            'hash' => $this->hash,
            'owner' => $this->owner,
            'symbols' => array_map(
                static fn (SourceSymbol $symbol): array => [
                    'name' => $symbol->name(),
                    'line' => $symbol->line(),
                ],
                $this->symbols,
            ),
            'references' => $this->references,
        ];
    }
}

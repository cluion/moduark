<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Source;

use Cluion\Moduark\Module;
use InvalidArgumentException;

final readonly class SourceReference
{
    /** @var class-string<Module> */
    private string $source;

    /** @var class-string<Module> */
    private string $target;

    public function __construct(
        string $source,
        string $target,
        private string $symbol,
        private string $file,
        private int $line,
    ) {
        if (! is_a($source, Module::class, true) || ! is_a($target, Module::class, true)) {
            throw new InvalidArgumentException('Source reference owners must extend Module.');
        }

        if (trim($symbol) === '') {
            throw new InvalidArgumentException('A source reference symbol must not be empty.');
        }

        if (trim($file) === '' || $line < 1) {
            throw new InvalidArgumentException('A source reference must have a file and positive line.');
        }

        $this->source = $source;
        $this->target = $target;
    }

    /**
     * @return class-string<Module>
     */
    public function source(): string
    {
        return $this->source;
    }

    /**
     * @return class-string<Module>
     */
    public function target(): string
    {
        return $this->target;
    }

    public function symbol(): string
    {
        return $this->symbol;
    }

    public function file(): string
    {
        return $this->file;
    }

    public function line(): int
    {
        return $this->line;
    }

    /**
     * @return array{
     *     source: class-string<Module>,
     *     target: class-string<Module>,
     *     symbol: string,
     *     file: string,
     *     line: int
     * }
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'target' => $this->target,
            'symbol' => $this->symbol,
            'file' => $this->file,
            'line' => $this->line,
        ];
    }
}

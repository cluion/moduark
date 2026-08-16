<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Source;

use Cluion\Moduark\Module;
use InvalidArgumentException;

final readonly class SourceSymbol
{
    /** @var class-string<Module> */
    private string $owner;

    public function __construct(
        private string $name,
        string $owner,
        private string $file,
        private int $line,
        private ?string $parent = null,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('A source symbol name must not be empty.');
        }

        if (! is_a($owner, Module::class, true)) {
            throw new InvalidArgumentException('A source symbol owner must extend Module.');
        }

        if (trim($file) === '' || $line < 1) {
            throw new InvalidArgumentException('A source symbol must have a file and positive line.');
        }

        if ($this->parent !== null && trim($this->parent) === '') {
            throw new InvalidArgumentException('A source symbol parent must be null or a non-empty class name.');
        }

        $this->owner = $owner;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return class-string<Module>
     */
    public function owner(): string
    {
        return $this->owner;
    }

    public function file(): string
    {
        return $this->file;
    }

    public function line(): int
    {
        return $this->line;
    }

    public function parent(): ?string
    {
        return $this->parent;
    }

    /**
     * @return array{
     *     name: string,
     *     owner: class-string<Module>,
     *     file: string,
     *     line: int,
     *     parent: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'owner' => $this->owner,
            'file' => $this->file,
            'line' => $this->line,
            'parent' => $this->parent,
        ];
    }
}

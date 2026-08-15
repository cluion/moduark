<?php

declare(strict_types=1);

namespace Cluion\Moduark\Graph;

use Cluion\Moduark\Module;
use InvalidArgumentException;

final readonly class ModuleGraphNode
{
    /** @var class-string<Module> */
    private string $moduleClass;

    /**
     * @param string $moduleClass
     */
    public function __construct(
        private string $name,
        string $moduleClass,
        private ?string $path,
        private bool $discovered,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('A Module graph node name must not be empty.');
        }

        if (! is_a($moduleClass, Module::class, true)) {
            throw new InvalidArgumentException('A Module graph node class must extend Module.');
        }

        if ($discovered && ($path === null || trim($path) === '')) {
            throw new InvalidArgumentException('A discovered Module graph node must have a source path.');
        }

        if (! $discovered && $path !== null) {
            throw new InvalidArgumentException('A missing Module graph node cannot have a source path.');
        }

        $this->moduleClass = $moduleClass;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return class-string<Module>
     */
    public function moduleClass(): string
    {
        return $this->moduleClass;
    }

    public function path(): ?string
    {
        return $this->path;
    }

    public function discovered(): bool
    {
        return $this->discovered;
    }

    /**
     * @return array{name: string, class: class-string<Module>, path: ?string, discovered: bool}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'class' => $this->moduleClass,
            'path' => $this->path,
            'discovered' => $this->discovered,
        ];
    }
}

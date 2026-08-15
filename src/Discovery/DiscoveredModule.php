<?php

declare(strict_types=1);

namespace Cluion\Moduark\Discovery;

use Cluion\Moduark\Module;

final readonly class DiscoveredModule
{
    /**
     * @param class-string<Module> $moduleClass
     */
    public function __construct(
        private string $name,
        private string $moduleClass,
        private string $path,
        private string $namespace,
    ) {
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

    public function path(): string
    {
        return $this->path;
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    /**
     * @return array{
     *     name: string,
     *     class: class-string<Module>,
     *     path: string,
     *     namespace: string
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'class' => $this->moduleClass,
            'path' => $this->path,
            'namespace' => $this->namespace,
        ];
    }
}

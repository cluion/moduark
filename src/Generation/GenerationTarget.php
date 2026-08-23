<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

final readonly class GenerationTarget
{
    /** @param array<string, bool|string> $parameters */
    public function __construct(
        private string $generatorId,
        private string $command,
        private string $className,
        private string $filePath,
        private string $moduleRelativePath,
        private bool $overwrite,
        private array $parameters,
    ) {
    }

    public function generatorId(): string
    {
        return $this->generatorId;
    }

    public function command(): string
    {
        return $this->command;
    }

    public function className(): string
    {
        return $this->className;
    }

    public function filePath(): string
    {
        return $this->filePath;
    }

    public function moduleRelativePath(): string
    {
        return $this->moduleRelativePath;
    }

    public function overwrite(): bool
    {
        return $this->overwrite;
    }

    /** @return array<string, bool|string> */
    public function parameters(): array
    {
        return $this->parameters;
    }
}

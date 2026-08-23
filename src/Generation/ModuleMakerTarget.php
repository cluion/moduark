<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

final readonly class ModuleMakerTarget
{
    public function __construct(
        private string $className,
        private string $filePath,
        private string $moduleRelativePath,
    ) {
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
}

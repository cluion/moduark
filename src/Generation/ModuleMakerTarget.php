<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

final readonly class ModuleMakerTarget
{
    public function __construct(
        private string $className,
        private string $filePath,
        private string $moduleRelativePath,
        private string $modulePath,
        private string $moduleName,
        private string $moduleNamespace,
        private string $localName,
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

    public function modulePath(): string
    {
        return $this->modulePath;
    }

    public function moduleName(): string
    {
        return $this->moduleName;
    }

    public function moduleNamespace(): string
    {
        return $this->moduleNamespace;
    }

    public function localName(): string
    {
        return $this->localName;
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

final readonly class ModuleMakerTarget
{
    public function __construct(
        private ModuleMakerType $type,
        private string $className,
        private string $filePath,
    ) {
    }

    public function type(): ModuleMakerType
    {
        return $this->type;
    }

    public function command(): string
    {
        return $this->type->command();
    }

    public function className(): string
    {
        return $this->className;
    }

    public function filePath(): string
    {
        return $this->filePath;
    }
}

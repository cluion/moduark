<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

final readonly class GenerationPlanOutputContext
{
    public function __construct(
        private string $command,
        private string $module,
        private string $generatorId,
        private ?string $preset = null,
    ) {
    }

    public function command(): string
    {
        return $this->command;
    }

    public function module(): string
    {
        return $this->module;
    }

    public function generatorId(): string
    {
        return $this->generatorId;
    }

    public function preset(): ?string
    {
        return $this->preset;
    }
}

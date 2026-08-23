<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

final readonly class GenerationPlan
{
    /** @param list<GenerationTarget> $targets */
    public function __construct(private array $targets)
    {
    }

    /** @return list<GenerationTarget> */
    public function targets(): array
    {
        return $this->targets;
    }
}

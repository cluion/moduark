<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

final readonly class GenerationPlanner
{
    public function __construct(
        private GeneratorRegistry $registry,
        private ModuleMakerTargetResolver $resolver,
    ) {
    }

    public function plan(
        string $module,
        string $type,
        string $name,
        GenerationOptions $options,
    ): GenerationPlan {
        $descriptor = $this->registry->resolve($type);
        $target = $this->resolver->resolve($module, $descriptor, $name);

        return $descriptor->plan($target, $options);
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use Cluion\Moduark\Exceptions\ModuleMakerFailed;

final readonly class GenerationPlanner
{
    public function __construct(
        private GeneratorRegistry $registry,
        private ModuleMakerTargetResolver $resolver,
        private GenerationPlanValidator $validator,
    ) {
    }

    public function plan(
        string $module,
        string $type,
        string $name,
        GenerationOptions $options,
    ): GenerationPlan {
        $descriptor = $this->registry->resolve($type);

        foreach ($options->providedOptions() as $option) {
            if (! in_array($option, $descriptor->supportedOptions(), true)) {
                throw ModuleMakerFailed::unsupportedOption(
                    $option,
                    $descriptor->id(),
                );
            }
        }

        $target = $this->resolver->resolve($module, $descriptor, $name);

        $plan = $descriptor->plan($target, $options);

        $this->validator->validate($descriptor, $target, $plan, $options);

        return $plan;
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use Cluion\Moduark\Exceptions\ModuleMakerFailed;

final class GenerationPlanValidator
{
    public function validate(
        GeneratorDescriptor $descriptor,
        ModuleMakerTarget $makerTarget,
        GenerationPlan $plan,
        GenerationOptions $options,
    ): void {
        if ($plan->targets() === []) {
            throw ModuleMakerFailed::invalidGeneratorPlan(
                $descriptor->id(),
                'the plan must contain at least one target.',
            );
        }

        foreach ($plan->targets() as $target) {
            $this->validateTarget($descriptor, $makerTarget, $target, $options);
        }
    }

    private function validateTarget(
        GeneratorDescriptor $descriptor,
        ModuleMakerTarget $makerTarget,
        GenerationTarget $target,
        GenerationOptions $options,
    ): void {
        $relative = $target->moduleRelativePath();
        $segments = explode('/', $relative);

        if (
            $relative === ''
            || str_contains($relative, '\\')
            || str_starts_with($relative, '/')
            || in_array('', $segments, true)
            || in_array('.', $segments, true)
            || in_array('..', $segments, true)
        ) {
            throw ModuleMakerFailed::invalidGeneratorPlan(
                $descriptor->id(),
                "target [{$relative}] must be a portable Module-relative path.",
            );
        }

        $expected = $this->normalize($makerTarget->modulePath().'/'.$relative);

        if ($this->normalize($target->filePath()) !== $expected) {
            throw ModuleMakerFailed::invalidGeneratorPlan(
                $descriptor->id(),
                "target [{$relative}] must resolve exactly inside the selected Module.",
            );
        }

        if ($this->hasLinkedAncestor($expected, $makerTarget->modulePath())) {
            throw ModuleMakerFailed::invalidGeneratorPlan(
                $descriptor->id(),
                "target [{$relative}] must not traverse a linked Module directory.",
            );
        }

        if ($target->overwrite() && ! $options->force) {
            throw ModuleMakerFailed::invalidGeneratorPlan(
                $descriptor->id(),
                "target [{$relative}] requests overwrite without --force.",
            );
        }

        if ($descriptor instanceof ModuleMakerType) {
            return;
        }

        if ($target->generatorId() !== $descriptor->id()) {
            throw ModuleMakerFailed::invalidGeneratorPlan(
                $descriptor->id(),
                "target [{$relative}] must retain generator ID [{$descriptor->id()}].",
            );
        }

        if ($target->command() !== null || $target->template() === null || $target->parameters() !== []) {
            throw ModuleMakerFailed::invalidGeneratorPlan(
                $descriptor->id(),
                "target [{$relative}] must use a template without Artisan delegate parameters.",
            );
        }
    }

    private function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        return strlen($path) > 1 ? rtrim($path, '/') : $path;
    }

    private function hasLinkedAncestor(string $path, string $modulePath): bool
    {
        $modulePath = $this->normalize($modulePath);
        $directory = dirname($path);

        while ($directory !== $modulePath) {
            if (is_link($directory)) {
                return true;
            }

            $parent = dirname($directory);

            if ($parent === $directory || ! str_starts_with($directory, $modulePath.'/')) {
                return true;
            }

            $directory = $parent;
        }

        return false;
    }
}

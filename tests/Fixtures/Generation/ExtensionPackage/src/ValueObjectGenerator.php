<?php

declare(strict_types=1);

namespace Moduark\GeneratorExtensionFixture;

use Cluion\Moduark\Generation\GenerationFileTemplate;
use Cluion\Moduark\Generation\GenerationOptions;
use Cluion\Moduark\Generation\GenerationPlan;
use Cluion\Moduark\Generation\GenerationTarget;
use Cluion\Moduark\Generation\GeneratorDescriptor;
use Cluion\Moduark\Generation\ModuleMakerTarget;

final readonly class ValueObjectGenerator implements GeneratorDescriptor
{
    public function id(): string
    {
        return 'value-object';
    }

    public function targetNamespace(): string
    {
        return 'ValueObjects';
    }

    public function supportedOptions(): array
    {
        return ['force'];
    }

    public function plan(
        ModuleMakerTarget $target,
        GenerationOptions $options,
    ): GenerationPlan {
        $segments = explode('\\', $target->className());
        $class = array_pop($segments);

        return new GenerationPlan([
            new GenerationTarget(
                $this->id(),
                null,
                $target->className(),
                $target->filePath(),
                $target->moduleRelativePath(),
                $options->force,
                [],
                new GenerationFileTemplate(dirname(__DIR__).'/stubs/value-object.stub', [
                    '{{ namespace }}' => implode('\\', $segments),
                    '{{ class }}' => $class,
                ]),
            ),
        ]);
    }
}

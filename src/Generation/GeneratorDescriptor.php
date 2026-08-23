<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

interface GeneratorDescriptor
{
    public function id(): string;

    public function targetNamespace(): string;

    /** @return list<string> */
    public function supportedOptions(): array;

    public function plan(ModuleMakerTarget $target, GenerationOptions $options): GenerationPlan;
}

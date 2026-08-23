<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

final readonly class GenerationOptions
{
    public function __construct(
        public bool $force,
        public bool $invokable,
        public bool $resource,
        public bool $api,
        public bool $factory,
        public bool $migration,
    ) {
    }
}

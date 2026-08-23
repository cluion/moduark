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
        public ?string $create,
        public ?string $table,
        public bool $intBacked,
        public bool $stringBacked,
        public bool $inbound,
        public bool $render,
        public bool $report,
        public bool $collection,
        public bool $jsonApi,
        public ?string $model,
        public ?string $guard,
        public bool $implicit,
        public ?string $event,
        public bool $queued,
    ) {
    }
}

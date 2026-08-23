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
        public bool $sync,
        public bool $batched,
        public ?string $markdown,
        public ?string $view,
        public bool $viewOnly,
        public bool $inline,
        public ?string $path,
        public ?string $extension,
        public bool $unit,
        public bool $test,
        public bool $pest,
        public bool $phpunit,
        public ?string $commandName = null,
    ) {
    }

    /** @return list<string> */
    public function providedOptions(): array
    {
        $provided = [];

        foreach ([
            'force' => $this->force,
            'invokable' => $this->invokable,
            'resource' => $this->resource,
            'api' => $this->api,
            'factory' => $this->factory,
            'migration' => $this->migration,
            'create' => $this->create !== null,
            'table' => $this->table !== null,
            'int' => $this->intBacked,
            'string' => $this->stringBacked,
            'inbound' => $this->inbound,
            'render' => $this->render,
            'report' => $this->report,
            'collection' => $this->collection,
            'json-api' => $this->jsonApi,
            'model' => $this->model !== null,
            'guard' => $this->guard !== null,
            'implicit' => $this->implicit,
            'event' => $this->event !== null,
            'queued' => $this->queued,
            'sync' => $this->sync,
            'batched' => $this->batched,
            'markdown' => $this->markdown !== null,
            'view' => $this->view !== null || $this->viewOnly,
            'inline' => $this->inline,
            'path' => $this->path !== null,
            'extension' => $this->extension !== null,
            'unit' => $this->unit,
            'test' => $this->test,
            'pest' => $this->pest,
            'phpunit' => $this->phpunit,
            'command' => $this->commandName !== null,
        ] as $option => $enabled) {
            if ($enabled) {
                $provided[] = $option;
            }
        }

        return $provided;
    }
}

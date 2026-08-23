<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use Cluion\Moduark\Exceptions\ModuleMakerFailed;
use Illuminate\Support\Str;
use ParseError;

enum ModuleMakerType: string implements GeneratorDescriptor
{
    case PhpCast = 'cast';
    case Channel = 'channel';
    case PhpClass = 'class';
    case Component = 'component';
    case Model = 'model';
    case Controller = 'controller';
    case PhpEnum = 'enum';
    case Event = 'event';
    case PhpException = 'exception';
    case Factory = 'factory';
    case PhpInterface = 'interface';
    case Job = 'job';
    case JobMiddleware = 'job-middleware';
    case Listener = 'listener';
    case Mail = 'mail';
    case HttpMiddleware = 'middleware';
    case Migration = 'migration';
    case Notification = 'notification';
    case Observer = 'observer';
    case Policy = 'policy';
    case HttpRequest = 'request';
    case HttpResource = 'resource';
    case ValidationRule = 'rule';
    case PhpScope = 'scope';
    case Seeder = 'seeder';
    case PhpTrait = 'trait';
    case View = 'view';

    public static function parse(string $type): self
    {
        return match (strtolower($type)) {
            self::PhpCast->value => self::PhpCast,
            self::Channel->value => self::Channel,
            self::PhpClass->value => self::PhpClass,
            self::Component->value => self::Component,
            self::Model->value => self::Model,
            self::Controller->value => self::Controller,
            self::PhpEnum->value => self::PhpEnum,
            self::Event->value => self::Event,
            self::PhpException->value => self::PhpException,
            self::Factory->value => self::Factory,
            self::PhpInterface->value => self::PhpInterface,
            self::Job->value => self::Job,
            self::JobMiddleware->value => self::JobMiddleware,
            self::Listener->value => self::Listener,
            self::Mail->value => self::Mail,
            self::HttpMiddleware->value => self::HttpMiddleware,
            self::Migration->value => self::Migration,
            self::Notification->value => self::Notification,
            self::Observer->value => self::Observer,
            self::Policy->value => self::Policy,
            self::HttpRequest->value => self::HttpRequest,
            self::HttpResource->value => self::HttpResource,
            self::ValidationRule->value => self::ValidationRule,
            self::PhpScope->value => self::PhpScope,
            self::Seeder->value => self::Seeder,
            self::PhpTrait->value => self::PhpTrait,
            self::View->value => self::View,
            default => throw ModuleMakerFailed::unsupportedType($type),
        };
    }

    public function command(): string
    {
        return 'make:'.$this->value;
    }

    public function id(): string
    {
        return $this->value;
    }

    public function namespace(): string
    {
        return match ($this) {
            self::PhpCast => 'Casts',
            self::Channel => 'Broadcasting',
            self::PhpClass => '',
            self::Component => 'View\\Components',
            self::Model => 'Models',
            self::Controller => 'Http\\Controllers',
            self::PhpEnum => 'Enums',
            self::Event => 'Events',
            self::PhpException => 'Exceptions',
            self::Factory => 'Database\\Factories',
            self::PhpInterface => 'Contracts',
            self::Job => 'Jobs',
            self::JobMiddleware => 'Jobs\\Middleware',
            self::Listener => 'Listeners',
            self::Mail => 'Mail',
            self::HttpMiddleware => 'Http\\Middleware',
            self::Migration => 'Database\\Migrations',
            self::Notification => 'Notifications',
            self::Observer => 'Observers',
            self::Policy => 'Policies',
            self::HttpRequest => 'Http\\Requests',
            self::HttpResource => 'Http\\Resources',
            self::ValidationRule => 'Rules',
            self::PhpScope => 'Models\\Scopes',
            self::Seeder => 'Database\\Seeders',
            self::PhpTrait => 'Concerns',
            self::View => '',
        };
    }

    public function targetNamespace(): string
    {
        return $this->namespace();
    }

    public function plan(ModuleMakerTarget $target, GenerationOptions $options): GenerationPlan
    {
        return match ($this) {
            self::Model => $this->modelPlan($target, $options),
            self::Controller => $this->controllerPlan($target, $options),
            self::Component => $this->componentPlan($target, $options),
            self::Factory => $this->standaloneFactoryPlan($target, $options),
            self::Migration => $this->standaloneMigrationPlan($target, $options),
            self::Seeder => $this->seederPlan($target, $options),
            self::View => $this->viewPlan($target, $options),
            self::PhpCast,
            self::Channel,
            self::PhpClass,
            self::PhpEnum,
            self::Event,
            self::PhpException,
            self::PhpInterface,
            self::Job,
            self::JobMiddleware,
            self::Listener,
            self::Mail,
            self::Notification,
            self::HttpMiddleware,
            self::Observer,
            self::Policy,
            self::HttpRequest,
            self::HttpResource,
            self::ValidationRule,
            self::PhpScope,
            self::PhpTrait => $this->singleTargetPlan($target, $options),
        };
    }

    private function modelPlan(
        ModuleMakerTarget $target,
        GenerationOptions $options,
    ): GenerationPlan {
        $this->rejectUnsupportedOptions($options, ['factory', 'migration']);
        $targets = [$this->modelTarget($target, $options)];

        if ($options->factory) {
            $targets[] = $this->factoryTarget($target, $options);
        }

        if ($options->migration) {
            $targets[] = $this->migrationTarget($target, $options);
        }

        return new GenerationPlan($targets);
    }

    private function controllerPlan(
        ModuleMakerTarget $target,
        GenerationOptions $options,
    ): GenerationPlan {
        $this->rejectUnsupportedOptions($options, ['invokable', 'resource', 'api']);

        if ($options->invokable && ($options->resource || $options->api)) {
            $conflicts = ['invokable'];

            if ($options->resource) {
                $conflicts[] = 'resource';
            }

            if ($options->api) {
                $conflicts[] = 'api';
            }

            throw ModuleMakerFailed::conflictingOptions($conflicts);
        }

        $parameters = [
            'name' => $target->className(),
            '--force' => $options->force,
        ];

        $parameters += [
            '--invokable' => $options->invokable,
            '--resource' => $options->resource,
            '--api' => $options->api,
        ];

        $parameters['--no-interaction'] = true;

        return new GenerationPlan([
            new GenerationTarget(
                $this->id(),
                $this->command(),
                $target->className(),
                $target->filePath(),
                $target->moduleRelativePath(),
                $options->force,
                array_filter(
                    $parameters,
                    static fn (bool|string $value): bool => $value !== false,
                ),
            ),
        ]);
    }

    private function componentPlan(
        ModuleMakerTarget $target,
        GenerationOptions $options,
    ): GenerationPlan {
        $this->rejectUnsupportedOptions($options, ['view', 'inline', 'path']);

        if ($options->inline && $options->viewOnly) {
            throw ModuleMakerFailed::conflictingComponentOptions(['inline', 'view']);
        }

        if ($options->inline && $options->path !== null) {
            throw ModuleMakerFailed::conflictingComponentOptions(['inline', 'path']);
        }

        $viewSegments = $this->componentViewSegments($target, $options->path);
        $viewRelativePath = 'resources/views/'.implode('/', $viewSegments).'.blade.php';
        $viewName = strtolower($target->moduleName()).'::'.implode('.', $viewSegments);
        $viewTarget = new GenerationTarget(
            'view',
            null,
            $viewName,
            $target->modulePath().'/'.$viewRelativePath,
            $viewRelativePath,
            $options->force,
            [],
            new GenerationFileTemplate($this->stubPath('module-component-view.stub'), []),
        );

        if ($options->viewOnly) {
            return new GenerationPlan([$viewTarget]);
        }

        $classSegments = explode('\\', $target->className());
        $class = array_pop($classSegments);
        $classTarget = new GenerationTarget(
            $this->id(),
            null,
            $target->className(),
            $target->filePath(),
            $target->moduleRelativePath(),
            $options->force,
            [],
            new GenerationFileTemplate(
                $this->stubPath($options->inline
                    ? 'module-component.inline.stub'
                    : 'module-component.stub'),
                [
                    '{{ namespace }}' => implode('\\', $classSegments),
                    '{{ class }}' => $class,
                    '{{ view }}' => $viewName,
                ],
            ),
        );

        return new GenerationPlan($options->inline
            ? [$classTarget]
            : [$classTarget, $viewTarget]);
    }

    private function viewPlan(
        ModuleMakerTarget $target,
        GenerationOptions $options,
    ): GenerationPlan {
        $this->rejectUnsupportedOptions($options, ['extension']);
        $extension = $options->extension ?? 'blade.php';

        if (preg_match('/\A[a-z0-9]+(?:\.[a-z0-9]+)*\z/D', $extension) !== 1) {
            throw ModuleMakerFailed::invalidViewExtension($extension);
        }

        $segments = array_map(
            static fn (string $segment): string => Str::kebab($segment),
            explode('\\', $target->localName()),
        );
        $relativePath = 'resources/views/'.implode('/', $segments).'.'.$extension;
        $viewName = strtolower($target->moduleName()).'::'.implode('.', $segments);

        return new GenerationPlan([
            new GenerationTarget(
                $this->id(),
                null,
                $viewName,
                $target->modulePath().'/'.$relativePath,
                $relativePath,
                $options->force,
                [],
                new GenerationFileTemplate($this->stubPath('module-component-view.stub'), []),
            ),
        ]);
    }

    private function standaloneFactoryPlan(
        ModuleMakerTarget $target,
        GenerationOptions $options,
    ): GenerationPlan {
        $this->rejectUnsupportedOptions($options, ['model']);

        if ($options->force) {
            throw ModuleMakerFailed::unsupportedOption('force', $this->value);
        }

        $segments = explode('\\', $target->localName());
        $input = array_pop($segments);
        $modelShort = str_ends_with($input, 'Factory')
            ? substr($input, 0, -7)
            : $input;

        if ($modelShort === '') {
            throw ModuleMakerFailed::invalidFactoryName($input);
        }

        $class = $modelShort.'Factory';
        $nestedNamespace = $segments === [] ? '' : '\\'.implode('\\', $segments);
        $nestedPath = $segments === [] ? '' : '/'.implode('/', $segments);
        $namespace = $target->moduleNamespace().'\\Database\\Factories'.$nestedNamespace;
        $model = $options->model === null
            ? $target->moduleNamespace().'\\Models'.$nestedNamespace.'\\'.$modelShort
            : ltrim($this->moduleModel(
                $target,
                $options->model,
                ModuleMakerFailed::invalidFactoryModel($options->model),
            ), '\\');

        return new GenerationPlan([
            new GenerationTarget(
                $this->id(),
                null,
                $namespace.'\\'.$class,
                $target->modulePath().'/Database/Factories'.$nestedPath.'/'.$class.'.php',
                'Database/Factories'.$nestedPath.'/'.$class.'.php',
                false,
                [],
                new GenerationFileTemplate($this->stubPath('module-factory.stub'), [
                    '{{ namespace }}' => $namespace,
                    '{{ model }}' => $model,
                    '{{ modelShort }}' => class_basename($model),
                    '{{ class }}' => $class,
                ]),
            ),
        ]);
    }

    private function seederPlan(
        ModuleMakerTarget $target,
        GenerationOptions $options,
    ): GenerationPlan {
        $this->rejectUnsupportedOptions($options, []);

        if ($options->force) {
            throw ModuleMakerFailed::unsupportedOption('force', $this->value);
        }

        $segments = explode('\\', $target->className());
        $class = array_pop($segments);

        return new GenerationPlan([
            new GenerationTarget(
                $this->id(),
                null,
                $target->className(),
                $target->filePath(),
                $target->moduleRelativePath(),
                false,
                [],
                new GenerationFileTemplate($this->stubPath('module-seeder.stub'), [
                    '{{ namespace }}' => implode('\\', $segments),
                    '{{ class }}' => $class,
                ]),
            ),
        ]);
    }

    private function standaloneMigrationPlan(
        ModuleMakerTarget $target,
        GenerationOptions $options,
    ): GenerationPlan {
        $this->rejectUnsupportedOptions($options, ['create', 'table']);

        if ($options->force) {
            throw ModuleMakerFailed::unsupportedOption('force', $this->value);
        }

        if (str_contains($target->localName(), '\\')) {
            throw ModuleMakerFailed::invalidMigrationName($target->localName());
        }

        if ($options->create !== null && $options->table !== null) {
            throw ModuleMakerFailed::conflictingMigrationOptions();
        }

        $name = Str::snake($target->localName());
        [$table, $create] = $this->migrationTableAndMode($name, $options);

        return new GenerationPlan([
            $this->standaloneMigrationTarget($target, $name, $table, $create),
        ]);
    }

    private function singleTargetPlan(
        ModuleMakerTarget $target,
        GenerationOptions $options,
    ): GenerationPlan {
        $allowed = match ($this) {
            self::PhpCast => ['inbound'],
            self::PhpClass => ['invokable'],
            self::PhpEnum => ['int', 'string'],
            self::PhpException => ['render', 'report'],
            self::HttpResource => ['collection', 'json-api'],
            self::Observer => ['model'],
            self::Policy => ['model', 'guard'],
            self::ValidationRule => ['implicit'],
            self::Job => ['sync', 'batched'],
            self::Listener => ['event', 'queued'],
            self::Event,
            self::Channel,
            self::JobMiddleware,
            self::Mail,
            self::Notification,
            self::PhpInterface,
            self::HttpMiddleware,
            self::HttpRequest,
            self::PhpScope,
            self::PhpTrait => [],
            default => [],
        };
        $this->rejectUnsupportedOptions($options, $allowed);

        if ($this === self::HttpMiddleware && $options->force) {
            throw ModuleMakerFailed::unsupportedOption('force', $this->value);
        }

        if ($this === self::HttpResource && $options->collection && $options->jsonApi) {
            throw ModuleMakerFailed::conflictingResourceOptions(['collection', 'json-api']);
        }

        if ($this === self::Job && $options->sync && $options->batched) {
            throw ModuleMakerFailed::conflictingJobOptions();
        }

        $parameters = [
            'name' => $target->className(),
            '--force' => $options->force,
            '--no-interaction' => true,
        ];

        if ($this === self::PhpCast) {
            $parameters['--inbound'] = $options->inbound;
        } elseif ($this === self::PhpClass) {
            $parameters['--invokable'] = $options->invokable;
        } elseif ($this === self::PhpEnum) {
            $parameters['--int'] = $options->intBacked;
            $parameters['--string'] = $options->stringBacked;
        } elseif ($this === self::PhpException) {
            $parameters['--render'] = $options->render;
            $parameters['--report'] = $options->report;
        } elseif ($this === self::HttpResource) {
            $parameters['--collection'] = $options->collection;
            $parameters['--json-api'] = $options->jsonApi;
        } elseif ($this === self::Observer) {
            if ($options->model !== null) {
                $parameters['--model'] = $this->observerModel($target, $options->model);
            }
        } elseif ($this === self::Policy) {
            if ($options->model !== null) {
                $parameters['--model'] = $this->policyModel($target, $options->model);
            }

            if ($options->guard !== null) {
                $parameters['--guard'] = $options->guard;
            }
        } elseif ($this === self::ValidationRule) {
            $parameters['--implicit'] = $options->implicit;
        } elseif ($this === self::Listener) {
            if ($options->event !== null) {
                $parameters['--event'] = $this->listenerEvent($target, $options->event);
            }

            $parameters['--queued'] = $options->queued;
        } elseif ($this === self::Job) {
            $parameters['--sync'] = $options->sync;
            $parameters['--batched'] = $options->batched;
        }

        return new GenerationPlan([
            new GenerationTarget(
                $this->id(),
                $this->command(),
                $target->className(),
                $target->filePath(),
                $target->moduleRelativePath(),
                $options->force,
                array_filter(
                    $parameters,
                    static fn (bool|string $value): bool => $value !== false,
                ),
            ),
        ]);
    }

    /** @param list<string> $allowed */
    private function rejectUnsupportedOptions(GenerationOptions $options, array $allowed): void
    {
        foreach ([
            'invokable' => $options->invokable,
            'resource' => $options->resource,
            'api' => $options->api,
            'factory' => $options->factory,
            'migration' => $options->migration,
            'create' => $options->create !== null,
            'table' => $options->table !== null,
            'int' => $options->intBacked,
            'string' => $options->stringBacked,
            'inbound' => $options->inbound,
            'render' => $options->render,
            'report' => $options->report,
            'collection' => $options->collection,
            'json-api' => $options->jsonApi,
            'model' => $options->model !== null,
            'guard' => $options->guard !== null,
            'implicit' => $options->implicit,
            'event' => $options->event !== null,
            'queued' => $options->queued,
            'sync' => $options->sync,
            'batched' => $options->batched,
            'markdown' => $options->markdown !== null,
            'view' => $options->view !== null || $options->viewOnly,
            'inline' => $options->inline,
            'path' => $options->path !== null,
            'extension' => $options->extension !== null,
        ] as $option => $enabled) {
            if ($enabled && ! in_array($option, $allowed, true)) {
                throw ModuleMakerFailed::unsupportedOption($option, $this->value);
            }
        }
    }

    /** @return list<string> */
    private function componentViewSegments(
        ModuleMakerTarget $target,
        ?string $path,
    ): array {
        $nameSegments = explode('\\', $target->localName());
        $name = array_pop($nameSegments);

        if ($path === null) {
            $segments = ['components', ...$nameSegments];
        } else {
            if (preg_match(
                '/\A[a-z][a-z0-9]*(?:-[a-z0-9]+)*(?:\/[a-z][a-z0-9]*(?:-[a-z0-9]+)*)*\z/D',
                $path,
            ) !== 1) {
                throw ModuleMakerFailed::invalidComponentPath($path);
            }

            $segments = explode('/', $path);
        }

        $segments[] = $name;

        return array_map(static fn (string $segment): string => Str::kebab($segment), $segments);
    }

    private function policyModel(ModuleMakerTarget $target, string $model): string
    {
        return $this->moduleModel(
            $target,
            $model,
            ModuleMakerFailed::invalidPolicyModel($model),
        );
    }

    private function observerModel(ModuleMakerTarget $target, string $model): string
    {
        return $this->moduleModel(
            $target,
            $model,
            ModuleMakerFailed::invalidObserverModel($model),
        );
    }

    private function listenerEvent(ModuleMakerTarget $target, string $event): string
    {
        return $this->moduleClass(
            $target,
            $event,
            'Events',
            ModuleMakerFailed::invalidListenerEvent($event),
        );
    }

    private function moduleModel(
        ModuleMakerTarget $target,
        string $model,
        ModuleMakerFailed $failure,
    ): string {
        return $this->moduleClass($target, $model, 'Models', $failure);
    }

    private function moduleClass(
        ModuleMakerTarget $target,
        string $name,
        string $namespace,
        ModuleMakerFailed $failure,
    ): string {
        $normalized = str_replace('\\', '/', $name);

        if (preg_match('/\A[A-Z][A-Za-z0-9]*(?:\/[A-Z][A-Za-z0-9]*)*\z/D', $normalized) !== 1) {
            throw $failure;
        }

        $segments = explode('/', $normalized);
        $shortName = end($segments);

        try {
            $tokens = token_get_all("<?php class {$shortName} {}", TOKEN_PARSE);

            if ($tokens === []) {
                throw $failure;
            }
        } catch (ParseError) {
            throw $failure;
        }

        return '\\'.$target->moduleNamespace().'\\'.$namespace.'\\'.str_replace('/', '\\', $normalized);
    }

    private function modelTarget(
        ModuleMakerTarget $target,
        GenerationOptions $options,
    ): GenerationTarget {
        return new GenerationTarget(
            $this->id(),
            $options->factory ? null : $this->command(),
            $target->className(),
            $target->filePath(),
            $target->moduleRelativePath(),
            $options->force,
            $options->factory ? [] : array_filter([
                'name' => $target->className(),
                '--force' => $options->force,
                '--no-interaction' => true,
            ], static fn (bool|string $value): bool => $value !== false),
            $options->factory ? $this->modelTemplate($target) : null,
        );
    }

    private function factoryTarget(
        ModuleMakerTarget $target,
        GenerationOptions $options,
    ): GenerationTarget {
        $segments = explode('\\', $target->localName());
        $model = array_pop($segments);
        $nestedNamespace = $segments === [] ? '' : '\\'.implode('\\', $segments);
        $nestedPath = $segments === [] ? '' : '/'.implode('/', $segments);
        $namespace = $target->moduleNamespace().'\\Database\\Factories'.$nestedNamespace;
        $class = $model.'Factory';

        return new GenerationTarget(
            'factory',
            null,
            $namespace.'\\'.$class,
            $target->modulePath().'/Database/Factories'.$nestedPath.'/'.$class.'.php',
            'Database/Factories'.$nestedPath.'/'.$class.'.php',
            $options->force,
            [],
            new GenerationFileTemplate($this->stubPath('module-factory.stub'), [
                '{{ namespace }}' => $namespace,
                '{{ model }}' => $target->className(),
                '{{ modelShort }}' => $model,
                '{{ class }}' => $class,
            ]),
        );
    }

    private function migrationTarget(
        ModuleMakerTarget $target,
        GenerationOptions $options,
    ): GenerationTarget {
        $model = class_basename($target->localName());
        $table = Str::snake(Str::pluralStudly($model));
        $name = 'create_'.$table.'_table';
        $directory = $target->modulePath().'/Database/Migrations';
        $path = $this->moduleMigrationPath($directory, $name);

        return new GenerationTarget(
            'migration',
            null,
            $name,
            $path,
            'Database/Migrations/'.basename($path),
            $options->force,
            [],
            new GenerationFileTemplate($this->stubPath('module-migration.create.stub'), [
                '{{ table }}' => $table,
            ]),
        );
    }

    /** @return array{?string, ?bool} */
    private function migrationTableAndMode(
        string $name,
        GenerationOptions $options,
    ): array {
        if ($options->create !== null) {
            return [$this->migrationTable($options->create), true];
        }

        if ($options->table !== null) {
            return [$this->migrationTable($options->table), false];
        }

        foreach (['/^create_(\\w+)_table$/', '/^create_(\\w+)$/'] as $pattern) {
            if (preg_match($pattern, $name, $matches) === 1) {
                return [$matches[1], true];
            }
        }

        foreach (['/.+_(?:to|from|in)_(\\w+)_table$/', '/.+_(?:to|from|in)_(\\w+)$/'] as $pattern) {
            if (preg_match($pattern, $name, $matches) === 1) {
                return [$matches[1], false];
            }
        }

        return [null, null];
    }

    private function migrationTable(string $table): string
    {
        if (preg_match('/\A[a-z][a-z0-9]*(?:_[a-z0-9]+)*\z/D', $table) !== 1) {
            throw ModuleMakerFailed::invalidMigrationTable($table);
        }

        return $table;
    }

    private function standaloneMigrationTarget(
        ModuleMakerTarget $target,
        string $name,
        ?string $table,
        ?bool $create,
    ): GenerationTarget {
        $directory = $target->modulePath().'/Database/Migrations';
        $path = $this->moduleMigrationPath($directory, $name);
        $stub = match ($create) {
            true => 'module-migration.create.stub',
            false => 'module-migration.update.stub',
            null => 'module-migration.stub',
        };

        return new GenerationTarget(
            $this->id(),
            null,
            $name,
            $path,
            'Database/Migrations/'.basename($path),
            false,
            [],
            new GenerationFileTemplate(
                $this->stubPath($stub),
                $table === null ? [] : ['{{ table }}' => $table],
            ),
        );
    }

    private function moduleMigrationPath(string $directory, string $name): string
    {
        $existing = glob($directory.'/*_'.$name.'.php') ?: [];
        sort($existing, SORT_STRING);

        if (count($existing) > 1) {
            throw ModuleMakerFailed::ambiguousMigration(
                $name,
                array_map('basename', $existing),
            );
        }

        return $existing[0] ?? $this->newMigrationPath($directory, $name);
    }

    private function modelTemplate(ModuleMakerTarget $target): GenerationFileTemplate
    {
        $modelSegments = explode('\\', $target->className());
        $model = array_pop($modelSegments);
        $modelNamespace = implode('\\', $modelSegments);
        $localSegments = explode('\\', $target->localName());
        array_pop($localSegments);
        $factoryNamespace = $target->moduleNamespace().'\\Database\\Factories';

        if ($localSegments !== []) {
            $factoryNamespace .= '\\'.implode('\\', $localSegments);
        }

        return new GenerationFileTemplate($this->stubPath('module-model.factory.stub'), [
            '{{ namespace }}' => $modelNamespace,
            '{{ factory }}' => $factoryNamespace.'\\'.$model.'Factory',
            '{{ factoryShort }}' => $model.'Factory',
            '{{ class }}' => $model,
        ]);
    }

    private function newMigrationPath(string $directory, string $name): string
    {
        $timestamp = time();

        do {
            $prefix = date('Y_m_d_His', $timestamp++);
        } while (glob($directory.'/'.$prefix.'_*.php'));

        return $directory.'/'.$prefix.'_'.$name.'.php';
    }

    private function stubPath(string $stub): string
    {
        return dirname(__DIR__, 2).'/stubs/'.$stub;
    }
}

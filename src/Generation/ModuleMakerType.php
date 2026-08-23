<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use Cluion\Moduark\Exceptions\ModuleMakerFailed;
use Illuminate\Support\Str;
use ParseError;

enum ModuleMakerType: string implements GeneratorDescriptor
{
    case PhpCast = 'cast';
    case PhpClass = 'class';
    case Model = 'model';
    case Controller = 'controller';
    case PhpEnum = 'enum';
    case PhpException = 'exception';
    case Factory = 'factory';
    case PhpInterface = 'interface';
    case HttpMiddleware = 'middleware';
    case Policy = 'policy';
    case HttpRequest = 'request';
    case HttpResource = 'resource';
    case ValidationRule = 'rule';
    case PhpScope = 'scope';
    case Seeder = 'seeder';
    case PhpTrait = 'trait';

    public static function parse(string $type): self
    {
        return match (strtolower($type)) {
            self::PhpCast->value => self::PhpCast,
            self::PhpClass->value => self::PhpClass,
            self::Model->value => self::Model,
            self::Controller->value => self::Controller,
            self::PhpEnum->value => self::PhpEnum,
            self::PhpException->value => self::PhpException,
            self::Factory->value => self::Factory,
            self::PhpInterface->value => self::PhpInterface,
            self::HttpMiddleware->value => self::HttpMiddleware,
            self::Policy->value => self::Policy,
            self::HttpRequest->value => self::HttpRequest,
            self::HttpResource->value => self::HttpResource,
            self::ValidationRule->value => self::ValidationRule,
            self::PhpScope->value => self::PhpScope,
            self::Seeder->value => self::Seeder,
            self::PhpTrait->value => self::PhpTrait,
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
            self::PhpClass => '',
            self::Model => 'Models',
            self::Controller => 'Http\\Controllers',
            self::PhpEnum => 'Enums',
            self::PhpException => 'Exceptions',
            self::Factory => 'Database\\Factories',
            self::PhpInterface => 'Contracts',
            self::HttpMiddleware => 'Http\\Middleware',
            self::Policy => 'Policies',
            self::HttpRequest => 'Http\\Requests',
            self::HttpResource => 'Http\\Resources',
            self::ValidationRule => 'Rules',
            self::PhpScope => 'Models\\Scopes',
            self::Seeder => 'Database\\Seeders',
            self::PhpTrait => 'Concerns',
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
            self::Factory => $this->standaloneFactoryPlan($target, $options),
            self::Seeder => $this->seederPlan($target, $options),
            self::PhpCast,
            self::PhpClass,
            self::PhpEnum,
            self::PhpException,
            self::PhpInterface,
            self::HttpMiddleware,
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
            self::Policy => ['model', 'guard'],
            self::ValidationRule => ['implicit'],
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
        } elseif ($this === self::Policy) {
            if ($options->model !== null) {
                $parameters['--model'] = $this->policyModel($target, $options->model);
            }

            if ($options->guard !== null) {
                $parameters['--guard'] = $options->guard;
            }
        } elseif ($this === self::ValidationRule) {
            $parameters['--implicit'] = $options->implicit;
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
        ] as $option => $enabled) {
            if ($enabled && ! in_array($option, $allowed, true)) {
                throw ModuleMakerFailed::unsupportedOption($option, $this->value);
            }
        }
    }

    private function policyModel(ModuleMakerTarget $target, string $model): string
    {
        return $this->moduleModel(
            $target,
            $model,
            ModuleMakerFailed::invalidPolicyModel($model),
        );
    }

    private function moduleModel(
        ModuleMakerTarget $target,
        string $model,
        ModuleMakerFailed $failure,
    ): string {
        $normalized = str_replace('\\', '/', $model);

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

        return '\\'.$target->moduleNamespace().'\\Models\\'.str_replace('/', '\\', $normalized);
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
        $existing = glob($directory.'/*_'.$name.'.php') ?: [];
        sort($existing, SORT_STRING);

        if (count($existing) > 1) {
            throw ModuleMakerFailed::ambiguousMigration(
                $name,
                array_map('basename', $existing),
            );
        }

        $path = $existing[0] ?? $this->newMigrationPath($directory, $name);

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

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Exceptions\ModuleMakerFailed;
use Cluion\Moduark\Generation\ModuleMakerTarget;
use Cluion\Moduark\Generation\ModuleMakerTargetResolver;
use Cluion\Moduark\Generation\ModuleMakerType;
use Illuminate\Console\Command;

final class ModuleMakeCommand extends Command
{
    /** @var string */
    protected $signature = 'moduark:make
        {module : Existing Module name}
        {type : Maker type: model or controller}
        {name : StudlyCase class name, optionally with nested segments}
        {--force : Overwrite an existing generated class}
        {--invokable : Generate an invokable controller}
        {--resource : Generate a resource controller}
        {--api : Generate an API controller without create and edit methods}';

    /** @var string */
    protected $description = 'Generate a model or controller inside an existing Module';

    public function __construct(private readonly ModuleMakerTargetResolver $resolver)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $module = $this->argument('module');
        $type = $this->argument('type');
        $name = $this->argument('name');

        if (! is_string($module) || ! is_string($type) || ! is_string($name)) {
            $this->components->error('The module, type, and name arguments must be strings.');

            return ExitPolicy::TOOL_ERROR;
        }

        try {
            $target = $this->resolver->resolve($module, $type, $name);
            $parameters = $this->parameters($target);
        } catch (ModuleMakerFailed $exception) {
            $this->components->error('Module Maker failed: '.$exception->getMessage());

            return ExitPolicy::TOOL_ERROR;
        }

        if ($this->option('force') !== true && is_file($target->filePath())) {
            $this->components->error(ucfirst($target->type()->value).' already exists.');

            return self::FAILURE;
        }

        $parameters['--no-interaction'] = true;

        return $this->call($target->command(), $parameters);
    }

    /** @return array<string, bool|string> */
    private function parameters(ModuleMakerTarget $target): array
    {
        $force = $this->option('force') === true;
        $invokable = $this->option('invokable') === true;
        $resource = $this->option('resource') === true;
        $api = $this->option('api') === true;

        if ($target->type() === ModuleMakerType::Model) {
            foreach (['invokable' => $invokable, 'resource' => $resource, 'api' => $api] as $option => $enabled) {
                if ($enabled) {
                    throw ModuleMakerFailed::unsupportedOption($option, $target->type()->value);
                }
            }

            return array_filter([
                'name' => $target->className(),
                '--force' => $force,
            ], static fn (bool|string $value): bool => $value !== false);
        }

        if ($invokable && ($resource || $api)) {
            $options = ['invokable'];

            if ($resource) {
                $options[] = 'resource';
            }

            if ($api) {
                $options[] = 'api';
            }

            throw ModuleMakerFailed::conflictingOptions($options);
        }

        return array_filter([
            'name' => $target->className(),
            '--force' => $force,
            '--invokable' => $invokable,
            '--resource' => $resource,
            '--api' => $api,
        ], static fn (bool|string $value): bool => $value !== false);
    }
}

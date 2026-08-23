<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Exceptions\ModuleMakerFailed;
use Cluion\Moduark\Generation\GenerationOptions;
use Cluion\Moduark\Generation\GenerationPlan;
use Cluion\Moduark\Generation\GenerationPlanner;
use Cluion\Moduark\Generation\GenerationPreflight;
use Cluion\Moduark\Generation\GenerationTarget;
use Illuminate\Console\Command;

final class ModuleMakeCommand extends Command
{
    /** @var string */
    protected $signature = 'moduark:make
        {module : Existing Module name}
        {type : Maker type: model or controller}
        {name : StudlyCase class name, optionally with nested segments}
        {--dry-run : Display the complete generation plan without writing files}
        {--force : Overwrite an existing generated class}
        {--invokable : Generate an invokable controller}
        {--resource : Generate a resource controller}
        {--api : Generate an API controller without create and edit methods}';

    /** @var string */
    protected $description = 'Generate a model or controller inside an existing Module';

    public function __construct(
        private readonly GenerationPlanner $planner,
        private readonly GenerationPreflight $preflight,
    ) {
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
            $plan = $this->planner->plan($module, $type, $name, new GenerationOptions(
                force: $this->option('force') === true,
                invokable: $this->option('invokable') === true,
                resource: $this->option('resource') === true,
                api: $this->option('api') === true,
            ));
        } catch (ModuleMakerFailed $exception) {
            $this->components->error('Module Maker failed: '.$exception->getMessage());

            return ExitPolicy::TOOL_ERROR;
        }

        $collisions = $this->preflight->collisions($plan);

        if ($collisions !== []) {
            foreach ($collisions as $collision) {
                $this->components->error(ucfirst($collision->generatorId()).' already exists.');
            }

            return self::FAILURE;
        }

        if ($this->option('dry-run') === true) {
            $this->renderPlan($plan);

            return self::SUCCESS;
        }

        foreach ($plan->targets() as $target) {
            $exitCode = $this->call($target->command(), $target->parameters());

            if ($exitCode !== self::SUCCESS) {
                return $exitCode;
            }
        }

        return self::SUCCESS;
    }

    private function renderPlan(GenerationPlan $plan): void
    {
        $this->components->info('Generation plan (dry run):');

        foreach ($plan->targets() as $target) {
            $action = $this->action($target);
            $this->line("  {$action} {$target->moduleRelativePath()}");
        }
    }

    private function action(GenerationTarget $target): string
    {
        return $target->overwrite() && is_file($target->filePath()) ? 'OVERWRITE' : 'CREATE';
    }
}

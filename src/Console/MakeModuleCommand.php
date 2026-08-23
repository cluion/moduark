<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Exceptions\ModuleGenerationFailed;
use Cluion\Moduark\Generation\GenerationExecutor;
use Cluion\Moduark\Generation\GenerationPlan;
use Cluion\Moduark\Generation\GenerationPreflight;
use Cluion\Moduark\Generation\GenerationTarget;
use Cluion\Moduark\Generation\ModuleScaffoldPlanner;
use Cluion\Moduark\Generation\ModuleScaffoldPreset;
use Illuminate\Console\Command;
use RuntimeException;

final class MakeModuleCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'moduark:make-module
        {name : The StudlyCase name of the Module}
        {--preset= : Scaffold preset: minimal, web, api, domain, or full}
        {--dry-run : Display the complete scaffold plan without writing files}';

    /**
     * @var string
     */
    protected $description = 'Create a Module from a deterministic scaffold preset';

    public function __construct(
        private readonly ModuleScaffoldPlanner $planner,
        private readonly GenerationPreflight $preflight,
        private readonly GenerationExecutor $executor,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->argument('name');

        if (! is_string($name)) {
            $this->components->error('The Module name must be a string.');

            return self::INVALID;
        }

        try {
            $preset = $this->preset();
            $plan = $this->planner->plan($name, $preset);
        } catch (ModuleGenerationFailed $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $collisions = $this->preflight->collisions($plan);

        if ($collisions !== []) {
            foreach ($collisions as $collision) {
                $this->components->error($collision->generatorId() === 'module'
                    ? ModuleGenerationFailed::alreadyExists($collision->filePath())->getMessage()
                    : "Module scaffold target [{$collision->moduleRelativePath()}] already exists.");
            }

            return self::FAILURE;
        }

        if ($this->option('dry-run') === true) {
            $this->renderPlan($preset, $plan);

            return self::SUCCESS;
        }

        $result = $this->executor->execute(
            $plan,
            static function (GenerationTarget $target): int {
                throw new RuntimeException(
                    "Scaffold target [{$target->generatorId()}] has no package-owned template.",
                );
            },
        );

        if (! $result->successful()) {
            if ($result->failureMessage() !== null) {
                $this->components->error('Module generation failed: '.$result->failureMessage());
            }

            if ($result->rollbackFailures() !== []) {
                $this->components->error(
                    'Module scaffold rollback failed for ['
                    .implode(', ', $result->rollbackFailures()).'].',
                );
            } elseif (count($plan->targets()) > 1 && $result->rollbackAttempted()) {
                $this->components->warn(
                    'Module scaffold failed; all planned filesystem changes were rolled back.',
                );
            }

            return $result->exitCode();
        }

        $targets = $plan->targets();
        $entry = $targets[0] ?? throw new RuntimeException('Module scaffold plan has no entry target.');
        $this->components->info("Module [{$entry->filePath()}] created successfully.");

        if ($preset !== ModuleScaffoldPreset::Minimal) {
            $this->components->info(sprintf(
                'Preset [%s] created %d Module-owned targets.',
                $preset->value,
                count($targets),
            ));
        }

        return self::SUCCESS;
    }

    private function preset(): ModuleScaffoldPreset
    {
        $preset = $this->option('preset');

        if ($preset === null) {
            return ModuleScaffoldPreset::Minimal;
        }

        if (! is_string($preset)) {
            throw ModuleGenerationFailed::unsupportedPreset('invalid');
        }

        return ModuleScaffoldPreset::parse($preset);
    }

    private function renderPlan(ModuleScaffoldPreset $preset, GenerationPlan $plan): void
    {
        $this->components->info("Module scaffold plan [{$preset->value}] (dry run):");

        foreach ($plan->targets() as $target) {
            $this->line('  CREATE '.$target->moduleRelativePath());
        }
    }
}

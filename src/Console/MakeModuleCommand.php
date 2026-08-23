<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Exceptions\ModuleGenerationFailed;
use Cluion\Moduark\Generation\GenerationExecutor;
use Cluion\Moduark\Generation\GenerationPlan;
use Cluion\Moduark\Generation\GenerationPlanExporter;
use Cluion\Moduark\Generation\GenerationPlanFormat;
use Cluion\Moduark\Generation\GenerationPlanOutputContext;
use Cluion\Moduark\Generation\GenerationPreflight;
use Cluion\Moduark\Generation\GenerationTarget;
use Cluion\Moduark\Generation\ModuleScaffoldPlanner;
use Cluion\Moduark\Generation\ModuleScaffoldPreset;
use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeModuleCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'moduark:make-module
        {name : The StudlyCase name of the Module}
        {--preset= : Scaffold preset: minimal, web, api, domain, or full}
        {--dry-run : Display the complete scaffold plan without writing files}
        {--format=text : Plan output format (text or json; json requires --dry-run)}';

    /**
     * @var string
     */
    protected $description = 'Create a Module from a deterministic scaffold preset';

    public function __construct(
        private readonly ModuleScaffoldPlanner $planner,
        private readonly GenerationPreflight $preflight,
        private readonly GenerationExecutor $executor,
        private readonly GenerationPlanExporter $exporter,
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

        $format = $this->format();

        if ($format === null) {
            return self::INVALID;
        }

        $rawPreset = $this->option('preset');
        $context = new GenerationPlanOutputContext(
            'moduark:make-module',
            $name,
            'module',
            is_string($rawPreset) ? $rawPreset : null,
        );

        if ($format === GenerationPlanFormat::Json && $this->option('dry-run') !== true) {
            $this->renderRaw($this->exporter->jsonFailure(
                $context,
                self::INVALID,
                'MOD-SCAFFOLD-OPTION-001',
                'The --format=json option is only available with --dry-run.',
            ));

            return self::INVALID;
        }

        try {
            $preset = $this->preset();
            $plan = $this->planner->plan($name, $preset);
        } catch (ModuleGenerationFailed $exception) {
            if ($format === GenerationPlanFormat::Json) {
                $this->renderRaw($this->exporter->jsonFailure(
                    $context,
                    self::FAILURE,
                    'MOD-SCAFFOLD-PLAN-001',
                    $exception->getMessage(),
                ));
            } else {
                $this->components->error($exception->getMessage());
            }

            return self::FAILURE;
        }

        $context = new GenerationPlanOutputContext(
            'moduark:make-module',
            $name,
            'module',
            $preset->value,
        );

        $collisions = $this->preflight->collisions($plan);

        if ($collisions !== []) {
            if ($format === GenerationPlanFormat::Json) {
                $this->renderRaw($this->exporter->json($context, $plan, $collisions));
            } else {
                foreach ($collisions as $collision) {
                    $this->components->error($collision->generatorId() === 'module'
                        ? ModuleGenerationFailed::alreadyExists($collision->filePath())->getMessage()
                        : "Module scaffold target [{$collision->moduleRelativePath()}] already exists.");
                }
            }

            return self::FAILURE;
        }

        if ($this->option('dry-run') === true) {
            $this->renderPlan($format, $context, $preset, $plan);

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

    private function renderPlan(
        GenerationPlanFormat $format,
        GenerationPlanOutputContext $context,
        ModuleScaffoldPreset $preset,
        GenerationPlan $plan,
    ): void {
        if ($format === GenerationPlanFormat::Json) {
            $this->renderRaw($this->exporter->json($context, $plan));

            return;
        }

        $this->components->info("Module scaffold plan [{$preset->value}] (dry run):");

        foreach ($this->exporter->textLines($plan) as $line) {
            $this->line('  '.$line);
        }
    }

    private function format(): ?GenerationPlanFormat
    {
        $format = $this->option('format');

        if (is_string($format)) {
            $resolved = GenerationPlanFormat::tryFrom($format);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        $this->components->error('The --format option must be text or json.');

        return null;
    }

    private function renderRaw(string $output): void
    {
        $this->output->writeln($output, OutputInterface::OUTPUT_RAW);
    }
}

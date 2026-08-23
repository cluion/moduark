<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Exceptions\ModuleMakerFailed;
use Cluion\Moduark\Generation\GenerationOptions;
use Cluion\Moduark\Generation\GenerationExecutor;
use Cluion\Moduark\Generation\GenerationPlan;
use Cluion\Moduark\Generation\GenerationPlanExporter;
use Cluion\Moduark\Generation\GenerationPlanFormat;
use Cluion\Moduark\Generation\GenerationPlanOutputContext;
use Cluion\Moduark\Generation\GenerationPlanner;
use Cluion\Moduark\Generation\GenerationPreflight;
use Cluion\Moduark\Generation\GenerationTarget;
use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

final class ModuleMakeCommand extends Command
{
    /** @var string */
    protected $signature = 'moduark:make
        {module : Existing Module name}
        {type : Maker type: cast, channel, class, component, controller, enum, event, exception, factory, interface, job, job-middleware, listener, mail, middleware, migration, model, notification, observer, policy, request, resource, rule, scope, seeder, test, trait, or view}
        {name : StudlyCase class name, optionally with nested segments}
        {--dry-run : Display the complete generation plan without writing files}
        {--format=text : Plan output format (text or json; json requires --dry-run)}
        {--force : Overwrite an existing generated class}
        {--factory : Generate a Module-owned factory for a model}
        {--migration : Generate a Module-owned create-table migration for a model}
        {--create= : Generate a standalone migration that creates the named table}
        {--table= : Generate a standalone migration that changes the named table}
        {--int : Generate an integer-backed enum}
        {--string : Generate a string-backed enum}
        {--inbound : Generate an inbound Eloquent cast}
        {--render : Generate an exception with an empty render method}
        {--report : Generate an exception with an empty report method}
        {--collection : Generate a resource collection}
        {--json-api : Generate a JSON:API resource}
        {--model= : Module-owned model for a factory, observer, or policy}
        {--guard= : Laravel authentication guard that a policy relies on}
        {--implicit : Generate an implicit validation rule}
        {--event= : Module-owned event that a listener handles}
        {--queued : Generate a queued listener}
        {--sync : Generate a synchronous job}
        {--batched : Generate a batchable queued job}
        {--markdown= : Related Markdown views are not supported by Module notification or mail Makers}
        {--view= : Related Blade views are not supported by the Module mail Maker}
        {--inline : Generate a Blade component with an inline view}
        {--path= : Module-relative Blade component view directory}
        {--extension= : File extension for a Module-owned Blade view}
        {--unit : Generate a Module-owned unit test}
        {--test : Generate a Module-owned matching feature test}
        {--pest : Generate a Module-owned Pest test}
        {--phpunit : Generate a Module-owned PHPUnit test}
        {--invokable : Generate an invokable class or controller}
        {--resource : Generate a resource controller}
        {--api : Generate an API controller without create and edit methods}';

    /** @var string */
    protected $description = 'Generate a supported Laravel artifact inside an existing Module';

    public function __construct(
        private readonly GenerationPlanner $planner,
        private readonly GenerationPreflight $preflight,
        private readonly GenerationExecutor $executor,
        private readonly GenerationPlanExporter $exporter,
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

        $format = $this->format();

        if ($format === null) {
            return ExitPolicy::TOOL_ERROR;
        }

        $context = new GenerationPlanOutputContext(
            'moduark:make',
            $module,
            strtolower($type),
        );

        if ($format === GenerationPlanFormat::Json && $this->option('dry-run') !== true) {
            $this->renderRaw($this->exporter->jsonFailure(
                $context,
                ExitPolicy::TOOL_ERROR,
                'MOD-GEN-OPTION-001',
                'The --format=json option is only available with --dry-run.',
            ));

            return ExitPolicy::TOOL_ERROR;
        }

        try {
            $explicitPest = $this->option('pest') === true;
            $explicitPhpunit = $this->option('phpunit') === true;
            $matchingTest = $this->option('test') === true;
            $pest = $explicitPest || (
                ! $explicitPhpunit
                && ($type === 'test' || $matchingTest)
                && $this->applicationUsesPest()
            );

            $plan = $this->planner->plan($module, $type, $name, new GenerationOptions(
                force: $this->option('force') === true,
                invokable: $this->option('invokable') === true,
                resource: $this->option('resource') === true,
                api: $this->option('api') === true,
                factory: $this->option('factory') === true,
                migration: $this->option('migration') === true,
                create: $this->optionalStringOption('create'),
                table: $this->optionalStringOption('table'),
                intBacked: $this->option('int') === true,
                stringBacked: $this->option('string') === true,
                inbound: $this->option('inbound') === true,
                render: $this->option('render') === true,
                report: $this->option('report') === true,
                collection: $this->option('collection') === true,
                jsonApi: $this->option('json-api') === true,
                model: $this->optionalStringOption('model'),
                guard: $this->optionalStringOption('guard'),
                implicit: $this->option('implicit') === true,
                event: $this->optionalStringOption('event'),
                queued: $this->option('queued') === true,
                sync: $this->option('sync') === true,
                batched: $this->option('batched') === true,
                markdown: $this->optionalStringOption('markdown'),
                view: $this->optionalStringOption('view'),
                viewOnly: $this->input->hasParameterOption('--view', true)
                    && $this->option('view') === null,
                inline: $this->option('inline') === true,
                path: $this->optionalStringOption('path'),
                extension: $this->optionalStringOption('extension'),
                unit: $this->option('unit') === true,
                test: $matchingTest,
                pest: $pest,
                phpunit: $explicitPhpunit,
            ));
        } catch (ModuleMakerFailed $exception) {
            $message = 'Module Maker failed: '.$exception->getMessage();

            if ($format === GenerationPlanFormat::Json) {
                $this->renderRaw($this->exporter->jsonFailure(
                    $context,
                    ExitPolicy::TOOL_ERROR,
                    'MOD-GEN-PLAN-001',
                    $message,
                ));
            } else {
                $this->components->error($message);
            }

            return ExitPolicy::TOOL_ERROR;
        }

        $collisions = $this->preflight->collisions($plan);

        if ($collisions !== []) {
            if ($format === GenerationPlanFormat::Json) {
                $this->renderRaw($this->exporter->json($context, $plan, $collisions));
            } else {
                foreach ($collisions as $collision) {
                    $this->components->error(ucfirst($collision->generatorId()).' already exists.');
                }
            }

            return self::FAILURE;
        }

        if ($this->option('dry-run') === true) {
            $this->renderPlan($format, $context, $plan);

            return self::SUCCESS;
        }

        $result = $this->executor->execute(
            $plan,
            function (GenerationTarget $target): int {
                $command = $target->command();

                if ($command === null) {
                    throw new RuntimeException(
                        "Generator [{$target->generatorId()}] has no executable command or template.",
                    );
                }

                return $this->call($command, $target->parameters());
            },
        );

        if (! $result->successful()) {
            if ($result->failureMessage() !== null) {
                $this->components->error('Module Maker failed: '.$result->failureMessage());
            }

            if ($result->rollbackFailures() !== []) {
                $this->components->error(
                    'Generation rollback failed for ['.implode(', ', $result->rollbackFailures()).'].',
                );
            } elseif (count($plan->targets()) > 1 && $result->rollbackAttempted()) {
                $this->components->warn('Generation failed; all planned filesystem changes were rolled back.');
            }

            return $result->exitCode();
        }

        foreach ($plan->targets() as $target) {
            if ($target->template() !== null) {
                $this->components->info(sprintf(
                    '%s [%s] created successfully.',
                    ucfirst($target->generatorId()),
                    $target->moduleRelativePath(),
                ));
            }
        }

        return self::SUCCESS;
    }

    private function renderPlan(
        GenerationPlanFormat $format,
        GenerationPlanOutputContext $context,
        GenerationPlan $plan,
    ): void {
        if ($format === GenerationPlanFormat::Json) {
            $this->renderRaw($this->exporter->json($context, $plan));

            return;
        }

        $this->components->info('Generation plan (dry run):');

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

    private function optionalStringOption(string $name): ?string
    {
        $value = $this->option($name);

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || trim($value) === '') {
            throw ModuleMakerFailed::invalidOptionValue($name);
        }

        return trim($value);
    }

    private function applicationUsesPest(): bool
    {
        return function_exists('Pest\\version')
            && file_exists($this->laravel->basePath('tests/Pest.php'));
    }
}

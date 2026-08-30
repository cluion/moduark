<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Export\ModuleExportMaterializer;
use Cluion\Moduark\Export\ModuleExportPlanExporter;
use Cluion\Moduark\Export\ModuleExportPlanner;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class ModuleExportCommand extends Command
{
    /** @var string */
    protected $signature = 'moduark:export
        {module : Active Module name}
        {--dry-run : Build the package export plan without writing files}
        {--target= : Portable application-relative package target}
        {--package= : Lowercase Composer vendor/name}
        {--namespace= : Target package PHP namespace}
        {--dependency=* : Explicit Module=vendor/package:constraint=>Namespace mapping}
        {--format=text : Output format: text or json}';

    /** @var string */
    protected $description = 'Plan or materialize a standalone Module package';

    public function __construct(
        private readonly ModuleExportPlanner $planner,
        private readonly ModuleExportPlanExporter $exporter,
        private readonly ModuleExportMaterializer $materializer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $format = $this->option('format');
        $dryRun = $this->option('dry-run') === true;

        if (! is_string($format) || ! in_array($format, ['text', 'json'], true)) {
            return $this->failure(
                is_string($format) ? $format : 'text',
                'The export output format must be text or json.',
                $dryRun,
            );
        }

        $module = $this->argument('module');
        $target = $this->option('target');
        $package = $this->option('package');
        $namespace = $this->option('namespace');
        $dependencyMappings = $this->option('dependency');

        if (! is_string($module)
            || ! is_string($target)
            || ! is_string($package)
            || ! is_string($namespace)
            || ! is_array($dependencyMappings)
            || $target === ''
            || $package === ''
            || $namespace === '') {
            return $this->failure(
                $format,
                'Module export requires --target, --package, and --namespace.',
                $dryRun,
            );
        }

        $dependencies = [];

        foreach ($dependencyMappings as $mapping) {
            if (! is_string($mapping)) {
                return $this->failure(
                    $format,
                    'Every export dependency mapping must be a string.',
                    $dryRun,
                );
            }

            $dependencies[] = $mapping;
        }

        try {
            $plan = $this->planner->plan($module, $target, $package, $namespace, $dependencies);
        } catch (InvalidArgumentException $exception) {
            return $this->failure($format, $exception->getMessage(), $dryRun);
        }

        $exitCode = $plan->ready()
            ? ExitPolicy::SUCCESS
            : ExitPolicy::VIOLATIONS_FOUND;

        if ($dryRun && $format === 'json') {
            return $this->json($this->exporter->json($plan, $exitCode), $exitCode);
        }

        if (! $plan->ready()) {
            if ($format === 'json') {
                return $this->json($this->exporter->jsonMaterializationBlocked($plan), $exitCode);
            }

            foreach ($this->exporter->textLines($plan) as $line) {
                $this->line($line);
            }

            $this->components->error(
                "Module [{$plan->module()->name()}] package export is blocked; no files were written.",
            );

            return $exitCode;
        }

        if (! $dryRun) {
            $result = $this->materializer->materialize($plan);

            if ($format === 'json') {
                $payload = $result->successful()
                    ? $this->exporter->jsonMaterialized($plan)
                    : $this->exporter->jsonMaterializationFailure($plan, $result);

                return $this->json(
                    $payload,
                    $result->successful() ? ExitPolicy::SUCCESS : ExitPolicy::TOOL_ERROR,
                );
            }

            if (! $result->successful()) {
                $this->components->error($result->error() ?? 'Module package export failed.');

                foreach ($result->rollbackFailures() as $failure) {
                    $this->line('ROLLBACK-FAILED '.$failure);
                }

                return ExitPolicy::TOOL_ERROR;
            }

            foreach ($this->exporter->textLines($plan) as $line) {
                $this->line($line);
            }

            $this->components->info(
                "Module [{$plan->module()->name()}] package exported to [{$plan->target()}].",
            );

            return ExitPolicy::SUCCESS;
        }

        foreach ($this->exporter->textLines($plan) as $line) {
            $this->line($line);
        }

        $this->components->info(
            "Module [{$plan->module()->name()}] package export plan is ready; no files were written.",
        );

        return $exitCode;
    }

    private function failure(string $format, string $message, bool $dryRun): int
    {
        if ($format === 'json') {
            return $this->json(
                $this->exporter->jsonFailure(ExitPolicy::TOOL_ERROR, $message, $dryRun),
                ExitPolicy::TOOL_ERROR,
            );
        }

        $this->components->error($message);

        return ExitPolicy::TOOL_ERROR;
    }

    private function json(string $payload, int $exitCode): int
    {
        $this->line($payload);

        return $exitCode;
    }
}

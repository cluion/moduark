<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Architecture\ExitPolicy;
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
        {--format=text : Output format: text or json}';

    /** @var string */
    protected $description = 'Plan a Module package export without writing files';

    public function __construct(
        private readonly ModuleExportPlanner $planner,
        private readonly ModuleExportPlanExporter $exporter,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $format = $this->option('format');

        if (! is_string($format) || ! in_array($format, ['text', 'json'], true)) {
            return $this->failure(
                is_string($format) ? $format : 'text',
                'The export output format must be text or json.',
            );
        }

        if ($this->option('dry-run') !== true) {
            return $this->failure($format, 'Module export currently requires --dry-run; no package files were written.');
        }

        $module = $this->argument('module');
        $target = $this->option('target');
        $package = $this->option('package');
        $namespace = $this->option('namespace');

        if (! is_string($module)
            || ! is_string($target)
            || ! is_string($package)
            || ! is_string($namespace)
            || $target === ''
            || $package === ''
            || $namespace === '') {
            return $this->failure(
                $format,
                'Module export requires --target, --package, and --namespace.',
            );
        }

        try {
            $plan = $this->planner->plan($module, $target, $package, $namespace);
        } catch (InvalidArgumentException $exception) {
            return $this->failure($format, $exception->getMessage());
        }

        $exitCode = $plan->ready()
            ? ExitPolicy::SUCCESS
            : ExitPolicy::VIOLATIONS_FOUND;

        if ($format === 'json') {
            return $this->json($this->exporter->json($plan, $exitCode), $exitCode);
        }

        foreach ($this->exporter->textLines($plan) as $line) {
            $this->line($line);
        }

        if ($plan->ready()) {
            $this->components->info(
                "Module [{$plan->module()->name()}] package export plan is ready; no files were written.",
            );
        } else {
            $this->components->error(
                "Module [{$plan->module()->name()}] package export plan is blocked; no files were written.",
            );
        }

        return $exitCode;
    }

    private function failure(string $format, string $message): int
    {
        if ($format === 'json') {
            return $this->json(
                $this->exporter->jsonFailure(ExitPolicy::TOOL_ERROR, $message),
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

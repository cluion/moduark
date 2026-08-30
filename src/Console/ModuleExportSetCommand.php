<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Export\ModuleExportSetMaterializer;
use Cluion\Moduark\Export\ModuleExportSetPlanExporter;
use Cluion\Moduark\Export\ModuleExportSetPlanner;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class ModuleExportSetCommand extends Command
{
    /** @var string */
    protected $signature = 'moduark:export-set
        {--package=* : Selected Module=vendor/package:constraint=>Namespace mapping}
        {--target=* : Selected Module=portable/path mapping}
        {--materialize : Write the complete package set after validating every plan}
        {--format=text : Output format: text or json}';

    /** @var string */
    protected $description = 'Plan or materialize a dependency-closed package set';

    public function __construct(
        private readonly ModuleExportSetPlanner $planner,
        private readonly ModuleExportSetPlanExporter $exporter,
        private readonly ModuleExportSetMaterializer $materializer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $format = $this->option('format');
        $materialize = $this->option('materialize') === true;

        if (! is_string($format) || ! in_array($format, ['text', 'json'], true)) {
            return $this->failure(
                is_string($format) ? $format : 'text',
                'The package-set export output format must be text or json.',
                ! $materialize,
            );
        }

        try {
            $packages = $this->strings($this->option('package'), 'package');
            $targets = $this->strings($this->option('target'), 'target');
            $plan = $this->planner->plan($packages, $targets);
        } catch (InvalidArgumentException $exception) {
            return $this->failure($format, $exception->getMessage(), ! $materialize);
        }

        $exitCode = $plan->ready()
            ? ExitPolicy::SUCCESS
            : ExitPolicy::VIOLATIONS_FOUND;

        if (! $materialize && $format === 'json') {
            $this->line($this->exporter->json($plan, $exitCode));

            return $exitCode;
        }

        if ($materialize && ! $plan->ready()) {
            if ($format === 'json') {
                $this->line($this->exporter->jsonMaterializationBlocked($plan));

                return $exitCode;
            }

            foreach ($this->exporter->textLines($plan) as $line) {
                $this->line($line);
            }

            $this->components->error('Package-set export is blocked; no files were written.');

            return $exitCode;
        }

        if ($materialize) {
            $result = $this->materializer->materialize($plan);

            if ($format === 'json') {
                $payload = $result->successful()
                    ? $this->exporter->jsonMaterialized($plan, $result)
                    : $this->exporter->jsonMaterializationFailure($plan, $result);
                $this->line($payload);

                return $result->successful() ? ExitPolicy::SUCCESS : ExitPolicy::TOOL_ERROR;
            }

            if (! $result->successful()) {
                $this->components->error($result->error() ?? 'Package-set export failed.');

                foreach ($result->publishedBeforeRollback() as $target) {
                    $this->line('PUBLISHED-BEFORE-ROLLBACK '.$target);
                }

                foreach ($result->rollbackFailures() as $failure) {
                    $this->line('ROLLBACK-FAILED '.$failure);
                }

                foreach ($result->remainingTargets() as $target) {
                    $this->line('REMAINING-TARGET '.$target);
                }

                return ExitPolicy::TOOL_ERROR;
            }

            foreach ($this->exporter->textLines($plan) as $line) {
                $this->line($line);
            }

            $this->components->info('Package set exported in dependency order.');

            return ExitPolicy::SUCCESS;
        }

        foreach ($this->exporter->textLines($plan) as $line) {
            $this->line($line);
        }

        if ($plan->ready()) {
            $this->components->info('Package-set export plan is ready; no files were written.');
        } else {
            $this->components->error('Package-set export is blocked; no files were written.');
        }

        return $exitCode;
    }

    /** @return list<string> */
    private function strings(mixed $values, string $option): array
    {
        if (! is_array($values)) {
            throw new InvalidArgumentException(
                "Every package-set --{$option} mapping must be a string.",
            );
        }

        $mappings = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                throw new InvalidArgumentException(
                    "Every package-set --{$option} mapping must be a string.",
                );
            }

            $mappings[] = $value;
        }

        return $mappings;
    }

    private function failure(string $format, string $message, bool $dryRun): int
    {
        if ($format === 'json') {
            $this->line($this->exporter->jsonFailure(ExitPolicy::TOOL_ERROR, $message, $dryRun));

            return ExitPolicy::TOOL_ERROR;
        }

        $this->components->error($message);

        return ExitPolicy::TOOL_ERROR;
    }
}

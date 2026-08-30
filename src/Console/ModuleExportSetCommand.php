<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Architecture\ExitPolicy;
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
        {--format=text : Output format: text or json}';

    /** @var string */
    protected $description = 'Build a read-only dependency-closed package-set export plan';

    public function __construct(
        private readonly ModuleExportSetPlanner $planner,
        private readonly ModuleExportSetPlanExporter $exporter,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $format = $this->option('format');

        if (! is_string($format) || ! in_array($format, ['text', 'json'], true)) {
            return $this->failure(
                is_string($format) ? $format : 'text',
                'The package-set export output format must be text or json.',
            );
        }

        try {
            $packages = $this->strings($this->option('package'), 'package');
            $targets = $this->strings($this->option('target'), 'target');
            $plan = $this->planner->plan($packages, $targets);
        } catch (InvalidArgumentException $exception) {
            return $this->failure($format, $exception->getMessage());
        }

        $exitCode = $plan->ready()
            ? ExitPolicy::SUCCESS
            : ExitPolicy::VIOLATIONS_FOUND;

        if ($format === 'json') {
            $this->line($this->exporter->json($plan, $exitCode));

            return $exitCode;
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

    private function failure(string $format, string $message): int
    {
        if ($format === 'json') {
            $this->line($this->exporter->jsonFailure(ExitPolicy::TOOL_ERROR, $message));

            return ExitPolicy::TOOL_ERROR;
        }

        $this->components->error($message);

        return ExitPolicy::TOOL_ERROR;
    }
}

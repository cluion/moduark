<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Extraction\ExtractabilityInspector;
use Cluion\Moduark\Extraction\ExtractabilityReport;
use Cluion\Moduark\Resources\ResourceInspector;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

final class ModuleDoctorCommand extends Command
{
    /** @var string */
    protected $signature = 'moduark:doctor
        {module? : Module name to diagnose}
        {--extractable : Diagnose whether one active Module can enter export dry-run}
        {--format=text : Output format: text or json}';

    /** @var string */
    protected $description = 'Diagnose Module runtime resources and prerequisites';

    public function __construct(
        private readonly ResourceInspector $inspector,
        private readonly ExtractabilityInspector $extractability,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $module = $this->argument('module');
        $module = is_string($module) && trim($module) !== '' ? $module : null;
        $format = $this->option('format');

        if (! is_string($format) || ! in_array($format, ['text', 'json'], true)) {
            return $this->failOutput($format, 'The doctor output format must be text or json.');
        }

        if ($this->option('extractable') === true) {
            return $this->extractability($module, $format);
        }

        try {
            $modules = $this->inspector->moduleSummaries($module);
            $issues = $this->inspector->issues($module);
        } catch (InvalidArgumentException $exception) {
            return $this->failOutput($format, $exception->getMessage());
        }

        $exitCode = $issues === [] ? ExitPolicy::SUCCESS : ExitPolicy::VIOLATIONS_FOUND;
        $payload = [
            'schema_version' => 1,
            'status' => $issues === [] ? 'healthy' : 'issues_found',
            'framework_version' => \Illuminate\Foundation\Application::VERSION,
            'framework_supported' => $this->inspector->frameworkSupported(),
            'cached' => $this->inspector->cached(),
            'modules' => $modules,
            'issues' => $issues,
            'exit_code' => $exitCode,
            'error' => null,
        ];

        if ($format === 'json') {
            return $this->json($payload, $exitCode);
        }

        $this->table(
            ['Module', 'State', 'Dependencies', 'Resources'],
            array_map(fn (array $summary): array => $this->summaryRow($summary), $modules),
        );

        if ($issues === []) {
            $this->components->info('Moduark doctor found no runtime resource issues.');
        } else {
            $this->table(
                ['Severity', 'Code', 'Message'],
                array_map(static fn (array $issue): array => array_values($issue), $issues),
            );
        }

        return $exitCode;
    }

    private function extractability(?string $module, string $format): int
    {
        if ($module === null) {
            return $this->extractabilityError(
                $format,
                'The --extractable option requires one active Module name.',
            );
        }

        try {
            $report = $this->extractability->inspect($module);
        } catch (InvalidArgumentException $exception) {
            return $this->extractabilityError($format, $exception->getMessage());
        }

        $exitCode = $report->readyForExportDryRun()
            ? ExitPolicy::SUCCESS
            : ExitPolicy::VIOLATIONS_FOUND;
        $payload = [
            ...$report->toArray(),
            'exit_code' => $exitCode,
            'error' => null,
        ];

        if ($format === 'json') {
            return $this->json($payload, $exitCode);
        }

        $this->table(
            ['Code', 'Category', 'Status', 'Message', 'Evidence'],
            array_map(
                static fn ($check): array => [
                    $check->code(),
                    $check->category(),
                    $check->status(),
                    $check->message(),
                    implode(PHP_EOL, $check->evidence()),
                ],
                $report->checks(),
            ),
        );

        if ($report->readyForExportDryRun()) {
            $this->components->info(
                "Module [{$report->module()->name()}] is ready for export dry-run planning.",
            );
        } else {
            $this->components->error(
                "Module [{$report->module()->name()}] is blocked from export dry-run planning.",
            );
        }

        return $exitCode;
    }

    private function extractabilityError(string $format, string $message): int
    {
        if ($format === 'json') {
            return $this->json([
                'schema_version' => ExtractabilityReport::SCHEMA_VERSION,
                'mode' => 'extractability',
                'status' => 'error',
                'module' => null,
                'checks' => [],
                'blockers' => [],
                'exit_code' => ExitPolicy::TOOL_ERROR,
                'error' => $message,
            ], ExitPolicy::TOOL_ERROR);
        }

        $this->components->error($message);

        return ExitPolicy::TOOL_ERROR;
    }

    private function failOutput(mixed $format, string $message): int
    {
        if ($format === 'json') {
            return $this->json([
                'schema_version' => 1,
                'status' => 'error',
                'framework_version' => \Illuminate\Foundation\Application::VERSION,
                'framework_supported' => $this->inspector->frameworkSupported(),
                'cached' => $this->inspector->cached(),
                'modules' => [],
                'issues' => [],
                'exit_code' => ExitPolicy::TOOL_ERROR,
                'error' => $message,
            ], ExitPolicy::TOOL_ERROR);
        }

        $this->components->error($message);

        return ExitPolicy::TOOL_ERROR;
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload, int $exitCode): int
    {
        try {
            $this->line(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } catch (JsonException $exception) {
            $this->components->error('Doctor output could not be encoded: '.$exception->getMessage());

            return ExitPolicy::TOOL_ERROR;
        }

        return $exitCode;
    }

    /**
     * @param array<string, mixed> $summary
     * @return array{string, string, string, string}
     */
    private function summaryRow(array $summary): array
    {
        $name = is_string($summary['name'] ?? null) ? $summary['name'] : '[invalid]';
        $state = is_string($summary['state'] ?? null) ? $summary['state'] : '[invalid]';
        $dependencies = is_array($summary['dependencies'] ?? null)
            ? array_values(array_filter($summary['dependencies'], 'is_string'))
            : [];
        $resourceCount = is_int($summary['resource_count'] ?? null)
            ? (string) $summary['resource_count']
            : '0';

        return [
            $name,
            $state,
            $dependencies === [] ? '—' : implode(', ', $dependencies),
            $resourceCount,
        ];
    }
}

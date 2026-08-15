<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Analysis\ArchitectureCheck;
use Cluion\Moduark\Analysis\CheckReport;
use Cluion\Moduark\Analysis\Export\JsonCheckReportExporter;
use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Architecture\Level;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\Violation;
use Cluion\Moduark\Exceptions\SourceAnalysisFailed;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

final class ModuleCheckCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'module:check
        {--level= : Temporarily use an architecture Level from 0 to 3}
        {--format=text : Output format (text or json)}';

    /**
     * @var string
     */
    protected $description = 'Check the application Module architecture';

    public function __construct(
        private readonly ArchitectureCheck $checker,
        private readonly ExitPolicy $exitPolicy,
        private readonly JsonCheckReportExporter $json,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $format = $this->format();

        if ($format === false) {
            return ExitPolicy::TOOL_ERROR;
        }

        $level = $this->level();

        if ($level === false) {
            $this->renderToolError(
                $format,
                'MOD-CHECK-OPTION-001',
                'The --level option must be an integer from 0 to 3.',
                null,
                'Pass one of --level=0, --level=1, --level=2, or --level=3.',
            );

            return ExitPolicy::TOOL_ERROR;
        }

        try {
            $report = $this->checker->check($level);
        } catch (SourceAnalysisFailed $exception) {
            if ($format === 'json') {
                $this->renderJson($this->json->exportToolError(
                    $exception->diagnosticCode(),
                    $exception->getMessage(),
                    $exception->location(),
                    $exception->suggestion(),
                ));
            } else {
                $this->renderSourceAnalysisFailure($exception);
            }

            return ExitPolicy::TOOL_ERROR;
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->renderToolError(
                $format,
                'MOD-CHECK-TOOL-001',
                'Architecture analysis could not be completed: '.$exception->getMessage(),
            );

            return ExitPolicy::TOOL_ERROR;
        }

        if ($format === 'json') {
            $this->renderJson($this->json->export($report, $this->exitPolicy));

            return $report->exitCode($this->exitPolicy);
        }

        $this->renderViolations($report->violations());

        if (! $report->complete()) {
            $rules = array_map(
                static fn (RuleId $rule): string => $rule->value,
                $report->unavailableRules(),
            );

            $this->components->error(sprintf(
                'Architecture analysis is incomplete at Level %d (%s).',
                $report->architecture()->level()->value,
                $report->architecture()->level()->label(),
            ));
            $this->line('Unavailable rule implementations: '.implode(', ', $rules));

            return ExitPolicy::TOOL_ERROR;
        }

        $errors = count($report->errors());

        if ($errors > 0) {
            $this->components->error(sprintf(
                'Architecture check failed with %d blocking violation%s.',
                $errors,
                $errors === 1 ? '' : 's',
            ));

            return $report->exitCode($this->exitPolicy);
        }

        $warnings = count($report->warnings());

        if ($warnings > 0) {
            $this->components->warn(sprintf(
                'Architecture check passed with %d warning%s.',
                $warnings,
                $warnings === 1 ? '' : 's',
            ));

            return $report->exitCode($this->exitPolicy);
        }

        $this->components->info(sprintf(
            'Architecture check passed: %d rules evaluated at Level %d (%s).',
            count($report->results()),
            $report->architecture()->level()->value,
            $report->architecture()->level()->label(),
        ));

        return $report->exitCode($this->exitPolicy);
    }

    private function renderSourceAnalysisFailure(SourceAnalysisFailed $exception): void
    {
        $this->components->error('Architecture source analysis could not be completed.');
        $this->line($exception->diagnosticCode().' '.$exception->getMessage());

        if ($exception->location() !== null) {
            $this->line('Location: '.$exception->location());
        }

        $this->line('Suggestion: '.$exception->suggestion());
        $this->line('Result: incomplete; no architecture pass result was produced.');
    }

    private function level(): Level|false|null
    {
        $value = $this->option('level');

        if ($value === null && ! $this->input->hasParameterOption('--level')) {
            return null;
        }

        if (! is_string($value) || preg_match('/\A[0-3]\z/', $value) !== 1) {
            return false;
        }

        return Level::from((int) $value);
    }

    private function format(): string|false
    {
        $value = $this->option('format');

        if (! is_string($value) || ! in_array($value, ['text', 'json'], true)) {
            $this->components->error('The --format option must be text or json.');

            return false;
        }

        return $value;
    }

    private function renderToolError(
        string $format,
        string $code,
        string $message,
        ?string $location = null,
        ?string $suggestion = null,
    ): void {
        if ($format === 'json') {
            $this->renderJson($this->json->exportToolError(
                $code,
                $message,
                $location,
                $suggestion,
            ));

            return;
        }

        $this->components->error($message);
    }

    private function renderJson(string $json): void
    {
        $this->output->writeln($json, OutputInterface::OUTPUT_RAW);
    }

    /**
     * @param list<Violation> $violations
     */
    private function renderViolations(array $violations): void
    {
        foreach ($violations as $violation) {
            $location = $violation->file() ?? '—';

            if ($violation->line() !== null) {
                $location .= ':'.$violation->line();
            }

            $this->newLine();
            $this->line(sprintf(
                '%s [%s] %s',
                $violation->code(),
                $violation->severity()->value,
                $violation->rule()->value,
            ));
            $this->line($violation->message());
            $this->line('Location: '.$location);

            if ($violation->consumer() !== null || $violation->target() !== null) {
                $this->line(sprintf(
                    'Modules: %s -> %s',
                    $violation->consumer() ?? '—',
                    $violation->target() ?? '—',
                ));
            }

            if ($violation->symbol() !== null) {
                $this->line('Evidence: '.$violation->symbol());
            }

            if ($violation->suggestion() !== null) {
                $this->line('Suggestion: '.$violation->suggestion());
            }
        }
    }
}

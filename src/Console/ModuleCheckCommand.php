<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Analysis\ArchitectureCheck;
use Cluion\Moduark\Analysis\CheckReport;
use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Architecture\Level;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\Violation;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

final class ModuleCheckCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'module:check
        {--level= : Temporarily use an architecture Level from 0 to 3}';

    /**
     * @var string
     */
    protected $description = 'Check the application Module architecture';

    public function __construct(
        private readonly ArchitectureCheck $checker,
        private readonly ExitPolicy $exitPolicy,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $level = $this->level();

        if ($level === false) {
            return ExitPolicy::TOOL_ERROR;
        }

        try {
            $report = $this->checker->check($level);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->components->error(
                'Architecture analysis could not be completed: '.$exception->getMessage(),
            );

            return ExitPolicy::TOOL_ERROR;
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

    private function level(): Level|false|null
    {
        $value = $this->option('level');

        if ($value === null && ! $this->input->hasParameterOption('--level')) {
            return null;
        }

        if (! is_string($value) || preg_match('/\A[0-3]\z/', $value) !== 1) {
            $this->components->error('The --level option must be an integer from 0 to 3.');

            return false;
        }

        return Level::from((int) $value);
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

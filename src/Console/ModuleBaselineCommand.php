<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Analysis\Baseline\ArchitectureBaseline;
use Cluion\Moduark\Analysis\Baseline\ArchitectureBaselineStore;
use Cluion\Moduark\Analysis\RawArchitectureCheck;
use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Architecture\Level;
use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Exceptions\SourceAnalysisFailed;
use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use RuntimeException;

final class ModuleBaselineCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'module:baseline
        {--level= : Temporarily use an architecture Level from 0 to 3}
        {--force : Replace an existing baseline with every current violation}
        {--prune : Only remove stale violations from an existing baseline}';

    /**
     * @var string
     */
    protected $description = 'Create or safely prune the application architecture baseline';

    public function __construct(
        private readonly RawArchitectureCheck $checker,
        private readonly ArchitectureBaselineStore $store,
        private readonly ModulesConfig $configuration,
        private readonly Application $application,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $level = $this->level();

        if ($level === false) {
            $this->components->error('The --level option must be an integer from 0 to 3.');

            return ExitPolicy::TOOL_ERROR;
        }

        if ((bool) $this->option('force') && (bool) $this->option('prune')) {
            $this->components->error('The --force and --prune options cannot be used together.');

            return ExitPolicy::TOOL_ERROR;
        }

        $path = $this->configuration->baselinePath();

        if ($path === null) {
            $this->components->error('The modules.architecture.baseline path is not configured.');

            return ExitPolicy::TOOL_ERROR;
        }

        $force = (bool) $this->option('force');
        $prune = (bool) $this->option('prune');

        if (! $force && ! $prune && file_exists($path)) {
            $this->components->error(
                "Architecture baseline [{$path}] already exists; use --prune for safe cleanup or --force after review.",
            );

            return ExitPolicy::TOOL_ERROR;
        }

        try {
            $existing = $prune ? $this->store->load($path) : null;

            if ($prune && $existing === null) {
                $this->components->error("Architecture baseline [{$path}] does not exist.");

                return ExitPolicy::TOOL_ERROR;
            }

            $report = $this->checker->check($level);

            if (! $report->complete()) {
                $this->components->error('Architecture analysis is incomplete; no baseline was written.');

                return ExitPolicy::TOOL_ERROR;
            }

            if ($prune) {
                /** @var ArchitectureBaseline $existing */
                $baseline = $existing->prune($report, $this->application->basePath());
                $removed = $existing->violationCount() - $baseline->violationCount();

                if ($removed === 0) {
                    $this->components->info('Architecture baseline has no stale violations to prune.');

                    return ExitPolicy::SUCCESS;
                }

                $this->store->write($path, $baseline);
                $this->components->info(sprintf(
                    'Pruned %d stale baseline violation%s from [%s].',
                    $removed,
                    $removed === 1 ? '' : 's',
                    $path,
                ));

                return ExitPolicy::SUCCESS;
            }

            $replaced = file_exists($path);
            $baseline = ArchitectureBaseline::fromReport($report, $this->application->basePath());
            $this->store->write($path, $baseline);
            $count = $baseline->violationCount();
            $this->components->info(sprintf(
                '%s architecture baseline with %d violation%s at [%s].',
                $replaced ? 'Replaced' : 'Created',
                $count,
                $count === 1 ? '' : 's',
                $path,
            ));

            return ExitPolicy::SUCCESS;
        } catch (SourceAnalysisFailed $exception) {
            $this->components->error('Architecture source analysis could not be completed: '.$exception->getMessage());

            return ExitPolicy::TOOL_ERROR;
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->components->error('Architecture baseline could not be updated: '.$exception->getMessage());

            return ExitPolicy::TOOL_ERROR;
        }
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
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Discovery\ModuleActivationSet;
use Cluion\Moduark\Discovery\ModuleDiscoverer;
use Cluion\Moduark\Lifecycle\Activation\ModuleActivationBlocker;
use Cluion\Moduark\Lifecycle\Activation\ModuleActivationIntent;
use Cluion\Moduark\Lifecycle\Activation\ModuleActivationPlan;
use Cluion\Moduark\Lifecycle\Activation\ModuleActivationPlanner;
use Cluion\Moduark\Lifecycle\Activation\ModuleActivationState;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

abstract class ModuleActivationCommand extends Command
{
    public function __construct(
        private readonly ModuleActivationPlanner $planner,
        private readonly ModuleDiscoverer $discoverer,
        private readonly ModulesConfig $configuration,
        private readonly ModuleActivationState $state,
    ) {
        parent::__construct();
    }

    abstract protected function intent(): ModuleActivationIntent;

    public function handle(): int
    {
        $format = $this->option('format');

        if (! is_string($format) || ! in_array($format, ['text', 'json'], true)) {
            return $this->failOutput($format, 'The activation output format must be text or json.');
        }

        if ($this->option('dry-run') !== true) {
            return $this->failOutput(
                $format,
                'Activation state mutation is not available; pass --dry-run to preview the change.',
            );
        }

        $module = $this->argument('module');

        if (! is_string($module) || trim($module) === '') {
            return $this->failOutput($format, 'The Module name must be a non-empty string.');
        }

        try {
            $inventory = $this->discoverer->discover(
                $this->configuration->path(),
                ModuleActivationSet::all(),
            );
            $plan = $this->planner->plan(
                $inventory,
                $this->state->activationSet(),
                $module,
                $this->intent(),
            );
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return $this->failOutput($format, $exception->getMessage());
        }

        $exitCode = $plan->executable()
            ? ExitPolicy::SUCCESS
            : ExitPolicy::VIOLATIONS_FOUND;

        if ($format === 'json') {
            return $this->json([
                'schema_version' => 1,
                'status' => $plan->executable() ? 'planned' : 'blocked',
                'operation' => $this->intent()->value,
                'dry_run' => true,
                'driver' => $this->state->driver()->value,
                'plan' => $plan->toArray(),
                'exit_code' => $exitCode,
                'error' => null,
            ], $exitCode);
        }

        $this->renderText($plan);

        return $exitCode;
    }

    private function renderText(ModuleActivationPlan $plan): void
    {
        $this->table(
            ['Field', 'Value'],
            [
                ['Operation', $this->intent()->value],
                ['Module', $plan->module()],
                ['Driver', $this->state->driver()->value],
                ['Dry run', 'yes'],
                ['No-op', $plan->noOp() ? 'yes' : 'no'],
                ['Before', $plan->before() === [] ? '—' : implode(', ', $plan->before())],
                ['After', $plan->after() === [] ? '—' : implode(', ', $plan->after())],
            ],
        );

        if ($plan->executable()) {
            $this->components->info('Activation dry-run is executable; no state was changed.');

            return;
        }

        $this->table(
            ['Code', 'Message'],
            array_map(
                static fn (ModuleActivationBlocker $blocker): array => [
                    $blocker->code()->value,
                    $blocker->message(),
                ],
                $plan->blockers(),
            ),
        );
        $this->components->error('Activation dry-run is blocked; no state was changed.');
    }

    private function failOutput(mixed $format, string $message): int
    {
        if ($format === 'json') {
            return $this->json([
                'schema_version' => 1,
                'status' => 'error',
                'operation' => $this->intent()->value,
                'dry_run' => $this->option('dry-run') === true,
                'driver' => $this->state->driver()->value,
                'plan' => null,
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
            $this->components->error('Activation output could not be encoded: '.$exception->getMessage());

            return ExitPolicy::TOOL_ERROR;
        }

        return $exitCode;
    }
}

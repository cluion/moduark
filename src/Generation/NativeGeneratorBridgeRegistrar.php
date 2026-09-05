<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use Cluion\Moduark\Configuration\ModulesConfig;
use Illuminate\Console\Application;
use Illuminate\Console\Command as IlluminateCommand;

final readonly class NativeGeneratorBridgeRegistrar
{
    public function __construct(
        private ModulesConfig $configuration,
        private NativeGeneratorBridgePlanner $planner,
        private NativeGeneratorBridgeState $state,
        private NativeGeneratorBridgeCommandSet $commandSet,
        private NativeGeneratorBridgeContainerPreparer $containerPreparer,
    ) {
    }

    public function register(Application $artisan): void
    {
        if (! $this->configuration->nativeGeneratorBridgeEnabled() || $this->state->active()) {
            return;
        }

        $originals = $this->state->preparedOriginals();
        $decorated = $this->state->preparedDecorated();
        $expected = count(ModuleMakerType::cases());

        if (count($originals) !== $expected
            || count($decorated) !== $expected
            || ! $this->planner->planForCommands($originals)->ready()) {
            $this->fail('Native bridge preparation did not produce the complete reviewed command set.');
            return;
        }

        try {
            $owners = $this->commandSet->ownerMap($artisan);
        } catch (\Throwable $exception) {
            $this->fail($exception->getMessage());
            return;
        }

        foreach (ModuleMakerType::cases() as $type) {
            $owner = $owners[$type->command()] ?? null;
            $expectedClass = $this->planner->expectedClass($type);

            if ($owner !== $expectedClass) {
                $actual = is_string($owner)
                    ? $owner
                    : (is_object($owner) ? $owner::class : get_debug_type($owner));
                $this->fail(
                    "Native command [{$type->command()}] owner is [{$actual}], expected [{$expectedClass}].",
                );
                return;
            }
        }

        foreach ($decorated as $command) {
            $original = $command->original();
            $original->setApplication($artisan);

            if ($original instanceof IlluminateCommand) {
                $original->setLaravel($artisan->getLaravel());
            }
        }

        $failure = $this->commandSet->replace($artisan, $originals, $decorated);

        if ($failure !== null) {
            $this->fail($failure);
            return;
        }

        $this->state->activate($decorated);
    }

    private function fail(string $message): void
    {
        $this->containerPreparer->restore();
        $this->state->fail($message);
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use Cluion\Moduark\Configuration\ModulesConfig;
use Illuminate\Container\Container;
use Symfony\Component\Console\Command\Command;

final readonly class NativeGeneratorBridgeContainerPreparer
{
    public function __construct(
        private Container $container,
        private ModulesConfig $configuration,
        private NativeGeneratorBridgePlanner $planner,
        private NativeGeneratorBridgeExecutor $executor,
        private NativeGeneratorBridgeState $state,
    ) {
    }

    public function prepare(): void
    {
        if (! $this->configuration->nativeGeneratorBridgeEnabled()
            || $this->state->preparedDecorated() !== []) {
            return;
        }

        $originals = [];
        $decorated = [];

        foreach (ModuleMakerType::cases() as $type) {
            $expectedClass = $this->planner->expectedClass($type);
            $original = $this->container->make($expectedClass);

            if ($original::class !== $expectedClass) {
                return;
            }

            $originals[$type->command()] = $original;
            $decorated[$type->command()] = new NativeGeneratorBridgeDecoratedCommand(
                $type,
                $original,
                $this->executor,
            );
        }

        if (! $this->planner->planForCommands($originals)->ready()) {
            return;
        }

        $this->state->prepare($originals, $decorated);
    }

    public function restore(): void
    {
        $this->state->discardPreparation();
    }
}

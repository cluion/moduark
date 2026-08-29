<?php

declare(strict_types=1);

namespace Cluion\Moduark\Lifecycle\Activation;

use Cluion\Moduark\Discovery\ModuleActivationSet;

final readonly class ModuleActivationState
{
    public function __construct(
        private ModuleActivationDriver $driver,
        private ModuleActivationSet $activationSet,
    ) {
    }

    public function driver(): ModuleActivationDriver
    {
        return $this->driver;
    }

    public function activationSet(): ModuleActivationSet
    {
        return $this->activationSet;
    }
}

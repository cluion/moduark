<?php

declare(strict_types=1);

namespace Cluion\Moduark\Lifecycle\Activation;

use Cluion\Moduark\Discovery\ModuleActivationSet;
use Cluion\Moduark\Registry\ModuleRegistry;

interface ModuleActivationStore
{
    /** @param list<string> $knownNames */
    public function load(array $knownNames): ModuleActivationSet;

    /** @param callable(): void $beforeCommit */
    public function commit(
        ModuleRegistry $inventory,
        ModuleActivationSet $expected,
        ModuleActivationPlan $plan,
        callable $beforeCommit,
    ): void;

    public function path(): string;
}

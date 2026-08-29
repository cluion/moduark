<?php

declare(strict_types=1);

namespace Cluion\Moduark\Lifecycle\Activation;

use Cluion\Moduark\Exceptions\ModuleActivationMutationFailed;
use Cluion\Moduark\Registry\ModuleRegistry;

final readonly class ModuleActivationMutator
{
    public function __construct(private ModuleActivationCacheInvalidator $cacheInvalidator)
    {
    }

    public function apply(
        ModuleActivationPlan $plan,
        ModuleRegistry $inventory,
        ModuleActivationState $state,
    ): bool {
        if (! $plan->executable()) {
            throw ModuleActivationMutationFailed::invalidPlan();
        }

        if ($plan->noOp()) {
            return false;
        }

        $store = $state->store();

        if ($store === null) {
            throw ModuleActivationMutationFailed::unsupported($state->driver()->value);
        }

        $store->commit(
            $inventory,
            $state->activationSet(),
            $plan,
            $this->cacheInvalidator->invalidate(...),
        );

        return true;
    }
}

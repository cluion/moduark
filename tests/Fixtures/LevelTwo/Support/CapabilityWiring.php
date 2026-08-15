<?php

declare(strict_types=1);

namespace Tests\Fixtures\LevelTwo\Support;

use Cluion\Moduark\Capabilities\CapabilityPlan;
use Illuminate\Foundation\Application;

final class CapabilityWiring
{
    public function wire(Application $application, CapabilityPlan $plan): void
    {
        foreach ($plan->bindings() as $binding) {
            $application->bind($binding->port(), $binding->adapter());
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Fixtures\Lifecycle\Activation\Modules\CycleBeta;

use Cluion\Moduark\Module;
use Tests\Fixtures\Lifecycle\Activation\Modules\CycleAlpha\CycleAlphaModule;

final class CycleBetaModule extends Module
{
    /** @return list<class-string<Module>> */
    public function dependencies(): array
    {
        return [CycleAlphaModule::class];
    }
}

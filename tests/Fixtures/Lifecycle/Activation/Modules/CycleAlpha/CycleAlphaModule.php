<?php

declare(strict_types=1);

namespace Tests\Fixtures\Lifecycle\Activation\Modules\CycleAlpha;

use Cluion\Moduark\Module;
use Tests\Fixtures\Lifecycle\Activation\Modules\CycleBeta\CycleBetaModule;

final class CycleAlphaModule extends Module
{
    /** @return list<class-string<Module>> */
    public function dependencies(): array
    {
        return [CycleBetaModule::class];
    }
}

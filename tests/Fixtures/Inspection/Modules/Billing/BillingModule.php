<?php

declare(strict_types=1);

namespace Tests\Fixtures\Inspection\Modules\Billing;

use Cluion\Moduark\Module;
use Tests\Fixtures\Inspection\MissingBillingModule;

final class BillingModule extends Module
{
    /** @return list<class-string<Module>> */
    public function dependencies(): array
    {
        return [MissingBillingModule::class];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Fixtures\Lifecycle\Activation\Modules\Reports;

use Cluion\Moduark\Module;
use Tests\Fixtures\Lifecycle\Activation\Modules\Orders\OrdersModule;

final class ReportsModule extends Module
{
    /** @return list<class-string<Module>> */
    public function dependencies(): array
    {
        return [OrdersModule::class];
    }
}

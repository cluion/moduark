<?php

declare(strict_types=1);

namespace Workbench\App\Modules\Order;

use Cluion\Moduark\Module;
use Workbench\App\Modules\User\UserModule;

final class OrderModule extends Module
{
    /**
     * @return list<class-string<Module>>
     */
    public function dependencies(): array
    {
        return [UserModule::class];
    }
}

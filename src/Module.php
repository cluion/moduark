<?php

declare(strict_types=1);

namespace Cluion\Moduark;

use Illuminate\Support\ServiceProvider;

abstract class Module
{
    final public function __construct()
    {
    }

    /**
     * @return list<class-string<Module>>
     */
    public function dependencies(): array
    {
        return [];
    }

    /**
     * @return list<class-string<ServiceProvider>>
     */
    public function providers(): array
    {
        return [];
    }
}

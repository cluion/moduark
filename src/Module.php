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

    /**
     * @return list<CapabilityRequirement>
     */
    public function requires(): array
    {
        return [];
    }

    /**
     * @return list<class-string<Capability>>
     */
    public function provides(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function tables(): array
    {
        return [];
    }
}

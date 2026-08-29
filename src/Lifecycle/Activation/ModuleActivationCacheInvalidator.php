<?php

declare(strict_types=1);

namespace Cluion\Moduark\Lifecycle\Activation;

interface ModuleActivationCacheInvalidator
{
    public function invalidate(): void;
}

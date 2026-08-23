<?php

declare(strict_types=1);

namespace Moduark\GeneratorExtensionFixture;

use Cluion\Moduark\Generation\GeneratorRegistration;
use Illuminate\Support\ServiceProvider;

final class GeneratorExtensionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        GeneratorRegistration::register($this->app, ValueObjectGenerator::class);
    }
}

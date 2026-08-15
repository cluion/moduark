<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\ModuarkServiceProvider;
use Tests\TestCase;

final class PackageBaselineTest extends TestCase
{
    public function test_package_and_workbench_are_discovered(): void
    {
        self::assertArrayHasKey(ModuarkServiceProvider::class, $this->app->getLoadedProviders());
        self::assertTrue($this->app->bound(ModulesConfig::class));
        self::assertTrue($this->app->bound('moduark.workbench.loaded'));
    }

    public function test_zero_config_defaults_to_level_one(): void
    {
        $configuration = $this->app->make(ModulesConfig::class);

        self::assertSame(app_path('Modules'), $configuration->path());
        self::assertSame(1, $configuration->level());
        self::assertSame(1, config('modules.architecture.level'));
    }

    public function test_configuration_survives_config_cache(): void
    {
        try {
            $this->artisan('config:cache')->assertSuccessful();
            $this->refreshApplication();

            $configuration = $this->app->make(ModulesConfig::class);

            self::assertSame(1, $configuration->level());
            self::assertSame(app_path('Modules'), $configuration->path());
        } finally {
            $this->artisan('config:clear')->run();
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Lifecycle\ModuleLifecycleRegistrar;
use Cluion\Moduark\Lifecycle\ModuleOrderer;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\ModuarkServiceProvider;
use Illuminate\Contracts\Config\Repository;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PackageBaselineTest extends TestCase
{
    public function test_package_and_workbench_are_discovered(): void
    {
        $application = $this->application();

        self::assertArrayHasKey(ModuarkServiceProvider::class, $application->getLoadedProviders());
        self::assertTrue($application->bound(ModulesConfig::class));
        self::assertTrue($application->bound(ModuleMetadataCompiler::class));
        self::assertTrue($application->bound(ModuleOrderer::class));
        self::assertTrue($application->bound(ModuleLifecycleRegistrar::class));
        self::assertTrue($application->bound('moduark.workbench.loaded'));
    }

    public function test_zero_config_defaults_to_level_one(): void
    {
        $configuration = $this->application()->make(ModulesConfig::class);

        self::assertSame(app_path('Modules'), $configuration->path());
        self::assertSame(1, $configuration->level());
        self::assertSame(1, config('modules.architecture.level'));
    }

    public function test_configuration_survives_config_cache(): void
    {
        try {
            $this->command('config:cache')->assertSuccessful();
            $this->refreshApplication();

            $configuration = $this->application()->make(ModulesConfig::class);

            self::assertSame(1, $configuration->level());
            self::assertSame(app_path('Modules'), $configuration->path());
        } finally {
            $this->command('config:clear')->run();
        }
    }

    #[DataProvider('frameworkCacheCommands')]
    public function test_descriptor_payload_survives_framework_cache_commands(
        string $cacheCommand,
        string $clearCommand,
    ): void {
        $expected = $this->descriptorPayload();

        try {
            $this->command($cacheCommand)->assertSuccessful();
            $this->refreshApplication();

            $actual = $this->descriptorPayload();

            self::assertSame($expected, $actual);

            array_walk_recursive($actual, static function (mixed $value): void {
                self::assertTrue(is_scalar($value) || $value === null);
            });
        } finally {
            $this->command($clearCommand)->run();
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function frameworkCacheCommands(): iterable
    {
        yield 'config cache' => ['config:cache', 'config:clear'];
        yield 'optimize cache' => ['optimize', 'optimize:clear'];
    }

    /**
     * @return array<mixed>
     */
    private function descriptorPayload(): array
    {
        $configuration = $this->application()->make(Repository::class);
        $payload = $configuration->get('moduark.workbench.descriptors');

        if (! is_array($payload)) {
            throw new LogicException('The workbench descriptor payload is not available.');
        }

        return $payload;
    }
}

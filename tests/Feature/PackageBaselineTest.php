<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Architecture\EffectiveArchitecture;
use Cluion\Moduark\Architecture\Level;
use Cluion\Moduark\Architecture\RulePresets;
use Cluion\Moduark\Architecture\RuleResolver;
use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Discovery\ModuleDiscoverer;
use Cluion\Moduark\Lifecycle\ModuleLifecycleRegistrar;
use Cluion\Moduark\Lifecycle\ModuleOrderer;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\ModuarkServiceProvider;
use Cluion\Moduark\Registry\ModuleRegistry;
use Illuminate\Contracts\Config\Repository;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PackageBaselineTest extends TestCase
{
    public function test_package_and_workbench_providers_are_loaded(): void
    {
        $application = $this->application();

        self::assertArrayHasKey(ModuarkServiceProvider::class, $application->getLoadedProviders());
        self::assertTrue($application->bound(ModulesConfig::class));
        self::assertTrue($application->bound(RulePresets::class));
        self::assertTrue($application->bound(RuleResolver::class));
        self::assertTrue($application->bound(EffectiveArchitecture::class));
        self::assertTrue($application->bound(ModuleDiscoverer::class));
        self::assertTrue($application->bound(ModuleRegistry::class));
        self::assertTrue($application->bound(ModuleMetadataCompiler::class));
        self::assertTrue($application->bound(ModuleOrderer::class));
        self::assertTrue($application->bound(ModuleLifecycleRegistrar::class));
        self::assertTrue($application->bound('moduark.workbench.loaded'));
    }

    public function test_workbench_path_keeps_the_default_level_one(): void
    {
        $configuration = $this->application()->make(ModulesConfig::class);

        self::assertSame(dirname(__DIR__, 2).'/workbench/app/Modules', $configuration->path());
        self::assertSame(Level::Modular, $configuration->level());
        self::assertSame(1, config('modules.architecture.level'));
    }

    public function test_configuration_survives_config_cache(): void
    {
        $expected = $this->application()->make(EffectiveArchitecture::class)->toArray();

        try {
            $this->command('config:cache')->assertSuccessful();
            $this->refreshApplication();

            $configuration = $this->application()->make(ModulesConfig::class);

            self::assertSame(Level::Modular, $configuration->level());
            self::assertSame(dirname(__DIR__, 2).'/workbench/app/Modules', $configuration->path());
            self::assertSame(
                $expected,
                $this->application()->make(EffectiveArchitecture::class)->toArray(),
            );
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

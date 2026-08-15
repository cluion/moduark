<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Registry\ModuleRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Workbench\App\Modules\Order\OrderModule;
use Workbench\App\Modules\User\UserModule;
use Workbench\App\Modules\Workbench\WorkbenchModule;

final class ModuleDiscoveryCompatibilityTest extends TestCase
{
    public function test_workbench_modules_are_discovered_in_deterministic_order(): void
    {
        $registry = $this->application()->make(ModuleRegistry::class);

        self::assertSame([
            OrderModule::class,
            UserModule::class,
            WorkbenchModule::class,
        ], $registry->moduleClasses());

        self::assertSame([
            'Order',
            'User',
            'Workbench',
        ], array_column($registry->toArray(), 'name'));
    }

    #[DataProvider('frameworkCacheCommands')]
    public function test_registry_is_identical_after_framework_cache_commands(
        string $cacheCommand,
        string $clearCommand,
    ): void {
        $expected = $this->application()->make(ModuleRegistry::class)->toArray();

        try {
            $this->command($cacheCommand)->assertSuccessful();
            $this->refreshApplication();

            $actual = $this->application()->make(ModuleRegistry::class)->toArray();

            self::assertSame($expected, $actual);
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
        yield 'route cache' => ['route:cache', 'route:clear'];
    }
}

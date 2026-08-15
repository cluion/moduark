<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ModuleResourceIntegrationTest extends TestCase
{
    public function test_module_resources_are_loaded_by_laravel(): void
    {
        $this->assertProviderLifecycle();
        $this->assertRoutesViewsAndTranslations();
        $this->assertCommand();
        $this->assertMigrationPath();
    }

    public function test_module_migration_is_runnable(): void
    {
        try {
            self::assertFalse(Schema::hasTable('moduark_orders'));

            $this->command('migrate --force')->assertSuccessful();

            self::assertTrue(Schema::hasTable('moduark_orders'));
        } finally {
            Schema::dropIfExists('moduark_orders');
        }
    }

    #[DataProvider('frameworkCaches')]
    public function test_module_resources_survive_framework_caches(
        string $cacheCommand,
        string $clearCommand,
    ): void {
        try {
            if ($cacheCommand === 'route:cache') {
                $this->defineCacheRoutes(<<<'PHP'
<?php

declare(strict_types=1);
PHP);
            } else {
                $this->command($cacheCommand)->assertSuccessful();
                $this->refreshApplication();
            }

            $this->assertProviderLifecycle();
            $this->assertRoutesViewsAndTranslations();
            $this->assertCommand();
            $this->assertMigrationPath();
        } finally {
            $this->command($clearCommand)->run();
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function frameworkCaches(): iterable
    {
        yield 'config cache' => ['config:cache', 'config:clear'];
        yield 'route cache' => ['route:cache', 'route:clear'];
    }

    private function assertProviderLifecycle(): void
    {
        $configuration = $this->application()->make(Repository::class);

        self::assertTrue($configuration->get('moduark.order.provider.registered'));
        self::assertTrue($configuration->get('moduark.order.provider.booted'));
    }

    private function assertRoutesViewsAndTranslations(): void
    {
        $this->get('/__moduark-order')
            ->assertOk()
            ->assertSeeText('Order Module ready.');

        $this->get('/__moduark-order-api')
            ->assertOk()
            ->assertSeeText('Order Module ready.');

        self::assertSame('Order Module ready.', trans('order::messages.ready'));

        $views = $this->application()->make(Factory::class);

        self::assertTrue($views->exists('order::probe'));
    }

    private function assertCommand(): void
    {
        $this->command('order:probe')
            ->expectsOutputToContain('Order Module command ready.')
            ->assertSuccessful();
    }

    private function assertMigrationPath(): void
    {
        $migrator = $this->application()->make(Migrator::class);
        $expected = realpath(
            dirname(__DIR__, 2).'/workbench/app/Modules/Order/Database/Migrations',
        );

        self::assertNotFalse($expected);
        self::assertContains($expected, array_map('realpath', $migrator->paths()));
    }
}

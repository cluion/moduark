<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Resources\BuiltInResourceHandler;
use Cluion\Moduark\Resources\ModuleAssetManifest;
use Cluion\Moduark\Resources\ModuleResourceServiceProvider;
use Cluion\Moduark\Resources\ResourceDescriptor;
use Cluion\Moduark\Resources\ResourceManifest;
use Cluion\Moduark\Resources\ResourceOwnership;
use Cluion\Moduark\Resources\ResourceRuntime;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Workbench\App\Modules\Order\Events\OrderProbed;
use Workbench\App\Modules\Order\Models\OrderRecord;
use Workbench\App\Modules\Order\OrderModule;
use Workbench\App\Modules\Order\Policies\OrderPolicy;

final class ModuleResourceIntegrationTest extends TestCase
{
    public function test_external_manager_owns_conventions_but_explicit_resources_remain_moduark_owned(): void
    {
        $router = $this->application()->make(Router::class);
        $runtime = new ResourceRuntime(
            $this->application(),
            false,
            new ResourceOwnership(true),
        );
        $handler = new BuiltInResourceHandler('routes');
        $source = dirname(__DIR__, 2).'/workbench/app/Modules/Order/routes/admin.php';
        $before = count($router->getRoutes()->getRoutes());

        $handler->handle(new ResourceDescriptor(
            OrderModule::class,
            'routes',
            'routes/admin.php',
            $source,
            attributes: ['conventional' => true],
        ), $runtime);

        self::assertCount($before, $router->getRoutes()->getRoutes());

        $handler->handle(new ResourceDescriptor(
            OrderModule::class,
            'routes',
            'routes/external-opt-in.php',
            $source,
            attributes: ['group' => ['prefix' => '__moduark-explicit']],
        ), $runtime);

        self::assertCount($before + 1, $router->getRoutes()->getRoutes());
    }

    public function test_module_resources_are_loaded_by_laravel(): void
    {
        $this->assertProviderLifecycle();
        $this->assertRoutesViewsAndTranslations();
        $this->assertCommand();
        $this->assertMigrationPath();
        $this->assertExtendedResources();
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
            $this->assertExtendedResources();
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

        $this->get('/__moduark-order-admin')
            ->assertOk()
            ->assertSeeText('Order Module admin ready.');
    }

    private function assertCommand(): void
    {
        $this->command('order:probe')
            ->expectsOutputToContain('Order Module command ready.')
            ->assertSuccessful();

        $this->command('order:nested-probe')
            ->expectsOutputToContain('Order Module nested command ready.')
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

    private function assertExtendedResources(): void
    {
        self::assertSame('ready', config('order-module.runtime'));
        self::assertInstanceOf(OrderPolicy::class, Gate::getPolicyFor(OrderRecord::class));

        config(['moduark.order.listener_runs' => 0]);
        Event::dispatch(new OrderProbed);
        self::assertSame(1, config('moduark.order.listener_runs'));

        self::assertStringContainsString(
            'Order component ready.',
            Blade::render('<x-order::order-badge />'),
        );

        $manifest = $this->application()->make(ResourceManifest::class);
        $plugins = array_values(array_unique(array_map(
            static fn (ResourceDescriptor $resource): string => $resource->plugin(),
            $manifest->forModule(OrderModule::class),
        )));
        sort($plugins, SORT_STRING);

        self::assertSame([
            'assets',
            'commands',
            'components',
            'config',
            'events',
            'extensions',
            'factories',
            'listeners',
            'migrations',
            'policies',
            'providers',
            'routes',
            'seeders',
            'tests',
            'translations',
            'views',
        ], $plugins);

        $published = ServiceProvider::pathsToPublish(null, 'moduark-config');
        self::assertArrayHasKey(
            dirname(__DIR__, 2).'/workbench/app/Modules/Order/config/order.php',
            $published,
        );

        $assetInputs = $this->application()->make(ModuleAssetManifest::class)->inputs();
        self::assertContains(
            dirname(__DIR__, 2).'/workbench/app/Modules/Order/resources/js/order.js',
            $assetInputs,
        );
        self::assertContains(
            dirname(__DIR__, 2).'/workbench/app/Modules/Order/resources/css/order.css',
            $assetInputs,
        );
        self::assertNotContains(
            dirname(__DIR__, 2).'/workbench/app/Modules/Order/resources/public/order-icon.svg',
            $assetInputs,
        );

        $publishedAssets = ServiceProvider::pathsToPublish(null, 'moduark-assets');
        self::assertSame(
            public_path('vendor/order/order-icon.svg'),
            $publishedAssets[
                dirname(__DIR__, 2).'/workbench/app/Modules/Order/resources/public/order-icon.svg'
            ],
        );
    }

    public function test_repeated_resource_provider_boot_is_idempotent(): void
    {
        $provider = new ModuleResourceServiceProvider($this->application());
        $provider->boot();
        $provider->boot();

        config(['moduark.order.listener_runs' => 0]);
        Event::dispatch(new OrderProbed);

        self::assertSame(1, config('moduark.order.listener_runs'));
    }
}

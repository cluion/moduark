<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Analysis\Source\SourceAnalysisCacheStore;
use Cluion\Moduark\Analysis\Source\SourceIndexBuilder;
use Cluion\Moduark\Cache\ModuleCacheStore;
use Cluion\Moduark\Graph\CapabilityGraphBuilder;
use Cluion\Moduark\Graph\CombinedGraphBuilder;
use Cluion\Moduark\Graph\ModuleGraphBuilder;
use Cluion\Moduark\Lifecycle\OrderedModules;
use Cluion\Moduark\Listing\ModuleListBuilder;
use Cluion\Moduark\ModuarkServiceProvider;
use Cluion\Moduark\Package\ComposerPackageModuleDiscoverer;
use Cluion\Moduark\Package\PackageModuleCatalog;
use Cluion\Moduark\Registry\ModuleRegistry;
use Cluion\Moduark\Resources\ResourceManifest;
use Cluion\Moduark\Resources\ResourceManifestStatus;
use Composer\Autoload\ClassLoader;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\View\Factory;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use JsonException;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fixtures\PackageRuntime\PackageInventory\Capabilities\PackageInventoryAdapter;
use Tests\Fixtures\PackageRuntime\PackageInventory\Capabilities\PackageInventoryCapability;
use Tests\Fixtures\PackageRuntime\PackageInventory\Capabilities\PackageInventoryPort;
use Tests\Fixtures\PackageRuntime\PackageInventory\PackageInventoryModule;
use Tests\Fixtures\PackageRuntime\PackageInventory\PackageInventoryPackageServiceProvider;
use Tests\TestCase;
use Workbench\App\Modules\User\UserModule;
use Workbench\App\Modules\Workbench\WorkbenchModule;

final class PackageCanonicalActiveSetParityTest extends TestCase
{
    private string $fixtureDirectory;

    private string $manifestPath;

    private string $statePath;

    private ?string $originalState = null;

    /** @return list<class-string<ServiceProvider>> */
    protected function getPackageProviders($app): array
    {
        return [
            PackageRuntimeManifestServiceProvider::class,
            PackageInventoryPackageServiceProvider::class,
            ModuarkServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        $this->fixtureDirectory = sys_get_temp_dir().'/moduark-package-runtime-'.bin2hex(random_bytes(8));
        $this->manifestPath = $this->fixtureDirectory.'/installed.json';
        self::assertTrue(mkdir($this->fixtureDirectory, 0755, true));
        $this->writeManifest('acme/package-inventory');
        PackageRuntimeManifestServiceProvider::$manifestPath = $this->manifestPath;
        PackageRuntimeManifestServiceProvider::registerFixtureAutoload();

        $this->statePath = static::applicationBasePath().'/moduark-modules.json';

        if (is_file($this->statePath)) {
            $contents = file_get_contents($this->statePath);

            if ($contents === false) {
                throw new RuntimeException('Unable to preserve the Testbench activation state.');
            }

            $this->originalState = $contents;
        }

        file_put_contents($this->statePath, json_encode([
            'schema_version' => 1,
            'modules' => [
                'Order' => false,
                'User' => true,
                'Workbench' => true,
            ],
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        if (isset($this->app)) {
            foreach ($this->cachePaths() as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }

        if (is_file($this->statePath)) {
            unlink($this->statePath);
        }

        parent::tearDown();

        if ($this->originalState !== null) {
            file_put_contents($this->statePath, $this->originalState);
        }

        if (is_file($this->manifestPath)) {
            unlink($this->manifestPath);
        }

        if (is_dir($this->fixtureDirectory)) {
            rmdir($this->fixtureDirectory);
        }

        PackageRuntimeManifestServiceProvider::$manifestPath = '';
        PackageRuntimeManifestServiceProvider::unregisterFixtureAutoload();
    }

    public function test_package_module_uses_every_canonical_surface_cold_and_cached(): void
    {
        $this->assertCanonicalParity(cached: false);

        $this->command('moduark:cache')->assertSuccessful();
        $this->refreshApplication();

        $this->assertCanonicalParity(cached: true);
    }

    public function test_package_fingerprint_change_bypasses_the_loaded_cache(): void
    {
        $this->command('moduark:cache')->assertSuccessful();
        $before = $this->application()->make(PackageModuleCatalog::class)->fingerprint();
        $this->writeManifest('acme/package-inventory-renamed');

        $this->refreshApplication();

        $after = $this->application()->make(PackageModuleCatalog::class)->fingerprint();
        self::assertNotSame($before, $after);
        self::assertFalse($this->application()->make(ResourceManifestStatus::class)->cached());
        self::assertContains(
            PackageInventoryModule::class,
            $this->application()->make(ModuleRegistry::class)->moduleClasses(),
        );
    }

    /** @throws JsonException */
    private function assertCanonicalParity(bool $cached): void
    {
        $expected = $this->sorted([
            PackageInventoryModule::class,
            UserModule::class,
            WorkbenchModule::class,
        ]);
        $registry = $this->application()->make(ModuleRegistry::class);

        self::assertSame($expected, $this->sorted($registry->moduleClasses()));
        self::assertSame(
            ['PackageInventory', 'User', 'Workbench'],
            array_column($this->application()->make(ModuleListBuilder::class)->rows(), 0),
        );
        self::assertSame(
            $expected,
            $this->sorted(array_map(
                static fn ($node): string => $node->moduleClass(),
                $this->application()->make(ModuleGraphBuilder::class)->build()->discoveredNodes(),
            )),
        );
        self::assertSame(
            $expected,
            $this->sorted(array_map(
                static fn ($node): string => $node->moduleClass(),
                $this->application()->make(CombinedGraphBuilder::class)
                    ->build()
                    ->moduleGraph()
                    ->discoveredNodes(),
            )),
        );
        self::assertSame(
            $expected,
            $this->sorted(array_map(
                static fn ($descriptor): string => $descriptor->moduleClass(),
                $this->application()->make(OrderedModules::class)->all(),
            )),
        );

        $capabilityGraph = $this->application()->make(CapabilityGraphBuilder::class)->build();
        self::assertSame(
            $expected,
            $this->sorted(array_map(
                static fn ($node): string => $node->moduleClass(),
                $capabilityGraph->modules(),
            )),
        );
        self::assertCount(2, $capabilityGraph->edgesForCapability(PackageInventoryCapability::class));
        self::assertTrue($this->application()->bound(PackageInventoryPort::class));
        self::assertInstanceOf(
            PackageInventoryAdapter::class,
            $this->application()->make(PackageInventoryPort::class),
        );

        $owners = array_values(array_unique(array_map(
            static fn ($symbol): string => $symbol->owner(),
            $this->application()->make(SourceIndexBuilder::class)->build()->symbols(),
        )));
        self::assertSame($expected, $this->sorted($owners));

        $manifest = $this->application()->make(ResourceManifest::class);
        self::assertSame($expected, $this->sorted($manifest->moduleClasses()));
        self::assertSame(
            ['config', 'providers', 'routes', 'views'],
            $this->sorted(array_values(array_unique(array_map(
                static fn ($resource): string => $resource->plugin(),
                $manifest->forModule(PackageInventoryModule::class),
            )))),
        );
        self::assertSame($cached, $this->application()->make(ResourceManifestStatus::class)->cached());
        self::assertTrue(config('package_inventory.canonical'));
        self::assertSame(1, config('package_inventory.provider.register_count'));
        self::assertSame(1, config('package_inventory.provider.boot_count'));
        self::assertNotNull(
            $this->application()->make(Router::class)->getRoutes()->getByName('moduark.package-inventory'),
        );
        self::assertTrue(
            $this->application()->make(Factory::class)->exists('packageinventory::probe'),
        );

        [$packageCode, $packageError] = $this->commandOutput('moduark:disable', 'PackageInventory');
        self::assertSame(2, $packageCode);
        self::assertStringContainsString('always active while installed', $packageError);

        [$localCode, $localOutput] = $this->commandOutput('moduark:disable', 'Workbench');
        self::assertSame(0, $localCode);
        $local = json_decode($localOutput, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($local);
        $plan = $local['plan'] ?? null;
        self::assertIsArray($plan);
        $orderedModules = $plan['ordered_modules'] ?? null;
        self::assertIsArray($orderedModules);
        self::assertContains(PackageInventoryModule::class, $orderedModules);

        if ($cached) {
            $payload = require $this->application()->make(ModuleCacheStore::class)->path();
            self::assertIsArray($payload);
            self::assertSame(
                $this->application()->make(PackageModuleCatalog::class)->fingerprint(),
                $payload['package_fingerprint'],
            );
        }
    }

    /** @return array{int, string} */
    private function commandOutput(string $command, string $module): array
    {
        $output = new BufferedOutput;
        $exitCode = $this->application()->make(Kernel::class)->call($command, [
            'module' => $module,
            '--dry-run' => true,
            '--format' => 'json',
        ], $output);

        return [$exitCode, trim($output->fetch())];
    }

    private function writeManifest(string $package): void
    {
        $template = dirname(__DIR__).'/Fixtures/PackageRuntime/installed.json';
        $payload = json_decode((string) file_get_contents($template), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        $packages = $payload['packages'] ?? null;
        self::assertIsArray($packages);
        $row = $packages[0] ?? null;
        self::assertIsArray($row);
        $row['name'] = $package;
        $row['install-path'] = dirname(__DIR__)
            .'/Fixtures/PackageRuntime/PackageInventory';
        $packages[0] = $row;
        $payload['packages'] = $packages;
        self::assertIsInt(file_put_contents(
            $this->manifestPath,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ));
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        sort($values, SORT_STRING);

        return $values;
    }

    /** @return list<string> */
    private function cachePaths(): array
    {
        return [
            $this->application()->make(ModuleCacheStore::class)->path(),
            $this->application()->make(SourceAnalysisCacheStore::class)->path(),
            $this->application()->getCachedRoutesPath(),
            $this->application()->getCachedEventsPath(),
            $this->application()->bootstrapPath('cache/moduark-activation.lock'),
        ];
    }
}

final class PackageRuntimeManifestServiceProvider extends ServiceProvider
{
    public static string $manifestPath = '';

    private static ?ClassLoader $loader = null;

    public function register(): void
    {
        if (self::$manifestPath === '') {
            throw new RuntimeException('The package runtime fixture manifest is not configured.');
        }

        $this->app->instance(
            ComposerPackageModuleDiscoverer::class,
            new ComposerPackageModuleDiscoverer(self::$manifestPath),
        );
    }

    public static function registerFixtureAutoload(): void
    {
        if (self::$loader === null) {
            $loader = new ClassLoader;
            $loader->addPsr4(
                'Tests\\Fixtures\\PackageRuntime\\PackageInventory\\',
                dirname(__DIR__).'/Fixtures/PackageRuntime/PackageInventory/src',
            );
            $loader->register(true);
            self::$loader = $loader;
        }
    }

    public static function unregisterFixtureAutoload(): void
    {
        self::$loader?->unregister();
        self::$loader = null;
    }
}

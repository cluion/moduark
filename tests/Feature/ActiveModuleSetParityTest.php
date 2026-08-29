<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Analysis\Source\SourceAnalysisCacheStore;
use Cluion\Moduark\Analysis\Source\SourceIndexBuilder;
use Cluion\Moduark\Cache\ModuleCacheStore;
use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Discovery\ModuleActivationSet;
use Cluion\Moduark\Graph\CapabilityGraphBuilder;
use Cluion\Moduark\Graph\CombinedGraphBuilder;
use Cluion\Moduark\Graph\ModuleGraphBuilder;
use Cluion\Moduark\Lifecycle\OrderedModules;
use Cluion\Moduark\Listing\ModuleListBuilder;
use Cluion\Moduark\Package\PackageModuleCatalog;
use Cluion\Moduark\Registry\ModuleRegistry;
use Cluion\Moduark\Resources\ResourceInspector;
use Cluion\Moduark\Resources\ResourceManifest;
use Cluion\Moduark\Resources\ResourceManifestStatus;
use Illuminate\Contracts\Console\Kernel;
use JsonException;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;
use Workbench\App\Modules\Order\OrderModule;
use Workbench\App\Modules\Order\Providers\OrderServiceProvider;
use Workbench\App\Modules\User\UserModule;
use Workbench\App\Modules\Workbench\WorkbenchModule;

final class ActiveModuleSetParityTest extends TestCase
{
    private string $statePath;

    private ?string $originalState = null;

    protected function setUp(): void
    {
        $this->statePath = static::applicationBasePath().'/moduark-modules.json';

        if (is_file($this->statePath)) {
            $contents = file_get_contents($this->statePath);

            if ($contents === false) {
                throw new RuntimeException('Unable to preserve the Testbench activation state.');
            }

            $this->originalState = $contents;
        }

        $encoded = json_encode([
            'schema_version' => 1,
            'modules' => [
                'Order' => false,
                'User' => true,
                'Workbench' => true,
            ],
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($this->statePath, $encoded.PHP_EOL);

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
    }

    /** @throws JsonException */
    public function test_every_surface_uses_the_same_active_module_set_cold_and_cached(): void
    {
        $this->assertActiveSetParity(cached: false);

        $this->command('moduark:cache')->assertSuccessful();
        $this->refreshApplication();

        $this->assertActiveSetParity(cached: true);
    }

    /** @throws JsonException */
    private function assertActiveSetParity(bool $cached): void
    {
        $expectedClasses = $this->sorted([UserModule::class, WorkbenchModule::class]);
        $expectedNames = ['User', 'Workbench'];

        self::assertSame(
            $expectedClasses,
            $this->sorted($this->application()->make(ModuleRegistry::class)->moduleClasses()),
        );
        self::assertSame(
            $expectedNames,
            array_column($this->application()->make(ModuleListBuilder::class)->rows(), 0),
        );

        [$listCode, $listOutput] = $this->artisanOutput('moduark:list', []);
        self::assertSame(0, $listCode);
        self::assertStringContainsString('User', $listOutput);
        self::assertStringContainsString('Workbench', $listOutput);
        self::assertStringNotContainsString('Order', $listOutput);
        self::assertSame(
            $expectedClasses,
            $this->sorted(array_map(
                static fn ($node): string => $node->moduleClass(),
                $this->application()->make(ModuleGraphBuilder::class)->build()->discoveredNodes(),
            )),
        );

        foreach (['module', 'capability', 'combined'] as $view) {
            [$graphCode, $graphOutput] = $this->artisanOutput('moduark:graph', ['--view' => $view]);
            self::assertSame(0, $graphCode);
            self::assertStringContainsString('User', $graphOutput);
            self::assertStringContainsString('Workbench', $graphOutput);
            self::assertStringNotContainsString('Order', $graphOutput);
        }
        self::assertSame(
            $expectedClasses,
            $this->sorted(array_map(
                static fn ($node): string => $node->moduleClass(),
                $this->application()->make(CapabilityGraphBuilder::class)->build()->modules(),
            )),
        );
        self::assertSame(
            $expectedClasses,
            $this->sorted(array_map(
                static fn ($node): string => $node->moduleClass(),
                $this->application()->make(CombinedGraphBuilder::class)
                    ->build()
                    ->moduleGraph()
                    ->discoveredNodes(),
            )),
        );
        self::assertSame(
            $expectedClasses,
            $this->sorted(array_map(
                static fn ($descriptor): string => $descriptor->moduleClass(),
                $this->application()->make(OrderedModules::class)->all(),
            )),
        );

        $resourceManifest = $this->application()->make(ResourceManifest::class);
        self::assertSame($expectedClasses, $this->sorted($resourceManifest->moduleClasses()));
        self::assertSame([], $resourceManifest->forModule(OrderModule::class));
        self::assertSame($cached, $this->application()->make(ResourceManifestStatus::class)->cached());

        $owners = array_values(array_unique(array_map(
            static fn ($symbol): string => $symbol->owner(),
            $this->application()->make(SourceIndexBuilder::class)->build()->symbols(),
        )));
        self::assertSame($expectedClasses, $this->sorted($owners));

        self::assertFalse($this->application()->providerIsLoaded(OrderServiceProvider::class));
        self::assertNull(config('moduark.order.provider.registered'));
        self::assertNull(config('moduark.order.provider.booted'));
        self::assertFalse($this->application()->make(ModuleActivationSet::class)->includes('Order'));

        $resources = $this->application()->make(ResourceInspector::class)->moduleSummaries('Order');
        self::assertSame('disabled', $resources[0]['state']);
        self::assertSame(0, $resources[0]['resource_count']);
        self::assertSame([], $this->application()->make(ResourceInspector::class)->resources('Order'));

        [$inspectCode, $inspectOutput] = $this->artisanOutput('moduark:inspect', ['module' => 'Order']);
        self::assertSame(2, $inspectCode);
        self::assertStringContainsString('Module [Order] was not found', $inspectOutput);

        [$doctorCode, $doctor] = $this->jsonOutput('moduark:doctor', ['module' => 'Order']);
        self::assertSame(0, $doctorCode);
        self::assertSame($cached, $doctor['cached']);
        $doctorModules = $doctor['modules'] ?? null;
        self::assertIsArray($doctorModules);
        $doctorModule = $doctorModules[0] ?? null;
        self::assertIsArray($doctorModule);
        self::assertSame('disabled', $doctorModule['state']);

        [$resourceCode, $resourcePayload] = $this->jsonOutput('moduark:resources', ['module' => 'Order']);
        self::assertSame(0, $resourceCode);
        self::assertSame($cached, $resourcePayload['cached']);
        $resourceModules = $resourcePayload['modules'] ?? null;
        self::assertIsArray($resourceModules);
        $resourceModule = $resourceModules[0] ?? null;
        self::assertIsArray($resourceModule);
        self::assertSame('disabled', $resourceModule['state']);
        self::assertSame([], $resourcePayload['resources']);

        if ($cached) {
            $activation = $this->application()->make(ModuleActivationSet::class);
            $cache = $this->application()->make(ModuleCacheStore::class)->load(
                $this->application()->make(ModulesConfig::class)->path(),
                $activation->fingerprint(),
                $this->application()->make(PackageModuleCatalog::class)->fingerprint(),
            );

            self::assertNotNull($cache);
            self::assertSame($expectedClasses, $this->sorted($cache->registry()->moduleClasses()));
            self::assertSame($expectedClasses, $this->sorted($cache->resources()->moduleClasses()));
        }
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{int, string}
     */
    private function artisanOutput(string $command, array $arguments): array
    {
        $output = new BufferedOutput;
        $exitCode = $this->application()->make(Kernel::class)->call($command, $arguments, $output);

        return [$exitCode, trim($output->fetch())];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{int, array<string, mixed>}
     * @throws JsonException
     */
    private function jsonOutput(string $command, array $arguments): array
    {
        [$exitCode, $output] = $this->artisanOutput($command, [
            ...$arguments,
            '--format' => 'json',
        ]);
        $payload = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        $normalized = [];

        foreach ($payload as $key => $value) {
            self::assertIsString($key);
            $normalized[$key] = $value;
        }

        return [$exitCode, $normalized];
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

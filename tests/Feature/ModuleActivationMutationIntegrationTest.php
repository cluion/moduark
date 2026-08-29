<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Analysis\Source\SourceAnalysisCacheStore;
use Cluion\Moduark\Cache\ModuleCacheStore;
use Cluion\Moduark\Registry\ModuleRegistry;
use Illuminate\Contracts\Console\Kernel;
use JsonException;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

final class ModuleActivationMutationIntegrationTest extends TestCase
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
            unlink($this->statePath);
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        if (isset($this->app)) {
            $lockPath = $this->application()->bootstrapPath('cache/moduark-activation.lock');

            foreach ([
                $this->application()->getCachedRoutesPath(),
                $this->application()->getCachedEventsPath(),
                $this->application()->make(ModuleCacheStore::class)->path(),
                $this->application()->make(SourceAnalysisCacheStore::class)->path(),
                $lockPath,
            ] as $path) {
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
    public function test_mutation_invalidates_every_graph_cache_and_changes_the_next_boot(): void
    {
        self::assertContains('Workbench', $this->moduleNames());
        $this->createGraphCaches();

        $disabled = $this->activation('disable', 'Workbench');

        self::assertSame('applied', $disabled['status']);
        self::assertFalse($disabled['dry_run']);
        $this->assertGraphCachesAbsent();
        self::assertContains('Workbench', $this->moduleNames());

        $this->refreshApplication();

        self::assertNotContains('Workbench', $this->moduleNames());
        $this->createGraphCaches();

        $enabled = $this->activation('enable', 'Workbench');

        self::assertSame('applied', $enabled['status']);
        $this->assertGraphCachesAbsent();

        $this->refreshApplication();

        self::assertContains('Workbench', $this->moduleNames());
    }

    private function createGraphCaches(): void
    {
        $this->command('moduark:cache')->assertSuccessful();
        $this->command('moduark:check')->assertSuccessful();

        foreach ([
            $this->application()->getCachedRoutesPath(),
            $this->application()->getCachedEventsPath(),
        ] as $path) {
            $directory = dirname($path);

            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            file_put_contents($path, "<?php\n\nreturn [];\n");
        }

        foreach ($this->graphCachePaths() as $path) {
            self::assertFileExists($path);
        }
    }

    private function assertGraphCachesAbsent(): void
    {
        foreach ($this->graphCachePaths() as $path) {
            self::assertFileDoesNotExist($path);
        }
    }

    /** @return list<string> */
    private function graphCachePaths(): array
    {
        return [
            $this->application()->make(ModuleCacheStore::class)->path(),
            $this->application()->make(SourceAnalysisCacheStore::class)->path(),
            $this->application()->getCachedRoutesPath(),
            $this->application()->getCachedEventsPath(),
        ];
    }

    /** @return list<string> */
    private function moduleNames(): array
    {
        return array_column(
            $this->application()->make(ModuleRegistry::class)->toArray(),
            'name',
        );
    }

    /**
     * @return array<string, mixed>
     * @throws JsonException
     */
    private function activation(string $operation, string $module): array
    {
        $output = new BufferedOutput;
        $exitCode = $this->application()->make(Kernel::class)->call(
            "moduark:{$operation}",
            ['module' => $module, '--format' => 'json'],
            $output,
        );
        self::assertSame(0, $exitCode);
        $payload = json_decode(trim($output->fetch()), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        $normalized = [];

        foreach ($payload as $key => $value) {
            self::assertIsString($key);
            $normalized[$key] = $value;
        }

        return $normalized;
    }
}

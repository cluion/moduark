<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Cache\ModuleCacheManifest;
use Cluion\Moduark\Cache\ModuleCacheStore;
use Cluion\Moduark\Registry\ModuleRegistry;
use Tests\TestCase;

final class ModuleCacheCommandTest extends TestCase
{
    private string $probeDirectory;

    private string $probePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->probeDirectory = dirname(__DIR__, 2).'/workbench/app/Modules/CacheProbe';
        $this->probePath = $this->probeDirectory.'/CacheProbeModule.php';
        $this->removeProbe();
        $this->store()->clear();
    }

    protected function tearDown(): void
    {
        $this->removeProbe();

        if (isset($this->app)) {
            $this->store()->clear();
        }

        parent::tearDown();
    }

    public function test_module_cache_creates_a_versioned_manifest_and_clear_removes_it(): void
    {
        $this->command('module:cache')
            ->expectsOutputToContain('Module cache created successfully: 3 Modules cached.')
            ->assertSuccessful();

        self::assertFileExists($this->store()->path());

        $payload = require $this->store()->path();

        self::assertIsArray($payload);
        self::assertSame(ModuleCacheManifest::SCHEMA_VERSION, $payload['schema_version']);
        self::assertIsArray($payload['registry']);
        self::assertCount(3, $payload['registry']);
        self::assertIsArray($payload['descriptors']);
        self::assertCount(3, $payload['descriptors']);

        $this->command('module:clear')
            ->expectsOutputToContain('Module cache cleared successfully.')
            ->assertSuccessful();

        self::assertFileDoesNotExist($this->store()->path());
    }

    public function test_runtime_uses_the_manifest_until_it_is_cleared(): void
    {
        $this->command('module:cache')->assertSuccessful();
        $this->createProbe();
        $this->refreshApplication();

        self::assertNotContains('CacheProbe', $this->moduleNames());

        $this->command('module:clear')->assertSuccessful();
        $this->refreshApplication();

        self::assertContains('CacheProbe', $this->moduleNames());
    }

    public function test_rebuilding_never_reuses_the_loaded_manifest(): void
    {
        $this->command('module:cache')->assertSuccessful();
        $this->createProbe();
        $this->refreshApplication();

        self::assertNotContains('CacheProbe', $this->moduleNames());

        $this->command('module:cache')->assertSuccessful();
        $this->refreshApplication();

        self::assertContains('CacheProbe', $this->moduleNames());
    }

    public function test_optimize_and_optimize_clear_manage_the_module_cache(): void
    {
        $this->command('optimize')->assertSuccessful();
        self::assertFileExists($this->store()->path());

        $this->command('optimize:clear')->assertSuccessful();
        self::assertFileDoesNotExist($this->store()->path());
    }

    private function store(): ModuleCacheStore
    {
        return $this->application()->make(ModuleCacheStore::class);
    }

    /**
     * @return list<string>
     */
    private function moduleNames(): array
    {
        return array_column(
            $this->application()->make(ModuleRegistry::class)->toArray(),
            'name',
        );
    }

    private function createProbe(): void
    {
        mkdir($this->probeDirectory, 0777, true);
        file_put_contents($this->probePath, <<<'PHP'
<?php

declare(strict_types=1);

namespace Workbench\App\Modules\CacheProbe;

use Cluion\Moduark\Module;

final class CacheProbeModule extends Module
{
}
PHP);
    }

    private function removeProbe(): void
    {
        if (is_file($this->probePath)) {
            unlink($this->probePath);
        }

        if (is_dir($this->probeDirectory)) {
            rmdir($this->probeDirectory);
        }
    }
}

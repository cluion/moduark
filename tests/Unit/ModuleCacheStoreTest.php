<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Cache\ModuleCacheManifest;
use Cluion\Moduark\Cache\ModuleCacheStore;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Exceptions\ModuleCacheFailed;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use PHPUnit\Framework\TestCase;

final class ModuleCacheStoreTest extends TestCase
{
    private string $directory;

    private string $path;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/moduark-cache-'.bin2hex(random_bytes(8));
        $this->path = $this->directory.'/moduark.php';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function test_manifest_round_trips_as_deterministic_scalar_php(): void
    {
        $manifest = $this->manifest();
        $store = new ModuleCacheStore($this->path);

        $store->write($manifest);
        $first = file_get_contents($this->path);
        $store->write($manifest);
        $second = file_get_contents($this->path);

        self::assertIsString($first);
        self::assertSame($first, $second);
        self::assertStringStartsWith("<?php\n\ndeclare(strict_types=1);\n\nreturn ", $first);

        $loaded = $store->load('/workspace/app/Modules');

        self::assertNotNull($loaded);
        self::assertSame($manifest->toArray(), $loaded->toArray());
        self::assertSame(
            ['cache_beta'],
            $loaded->descriptors()[1]->tables(),
        );

        $payload = require $this->path;
        self::assertIsArray($payload);
        array_walk_recursive($payload, static function (mixed $value): void {
            self::assertTrue(is_scalar($value) || $value === null);
        });
    }

    public function test_manifest_for_another_module_root_is_not_used(): void
    {
        $store = new ModuleCacheStore($this->path);
        $store->write($this->manifest());

        self::assertNull($store->load('/different/app/Modules'));
    }

    public function test_manifest_with_an_unknown_schema_is_not_used(): void
    {
        mkdir($this->directory, 0777, true);
        file_put_contents($this->path, "<?php\n\nreturn ['schema_version' => 999];\n");

        self::assertNull((new ModuleCacheStore($this->path))->load('/workspace/app/Modules'));
    }

    public function test_schema_one_manifest_is_bypassed_after_table_metadata_is_added(): void
    {
        mkdir($this->directory, 0777, true);
        file_put_contents($this->path, "<?php\n\nreturn ['schema_version' => 1];\n");

        self::assertNull((new ModuleCacheStore($this->path))->load('/workspace/app/Modules'));
    }

    public function test_invalid_cache_payload_fails_with_the_cache_path(): void
    {
        mkdir($this->directory, 0777, true);
        file_put_contents($this->path, "<?php\n\nreturn ['schema_version' => 2];\n");

        $this->expectException(ModuleCacheFailed::class);
        $this->expectExceptionMessage("Module cache [{$this->path}] is invalid.");

        (new ModuleCacheStore($this->path))->load('/workspace/app/Modules');
    }

    public function test_current_cache_schema_revalidates_canonical_table_metadata(): void
    {
        $payload = $this->manifest()->toArray();
        $payload['descriptors'][1]['tables'] = ['users as u'];
        mkdir($this->directory, 0777, true);
        file_put_contents(
            $this->path,
            "<?php\n\nreturn ".var_export($payload, true).";\n",
        );

        $this->expectException(ModuleCacheFailed::class);
        $this->expectExceptionMessage("Module cache [{$this->path}] is invalid.");

        (new ModuleCacheStore($this->path))->load('/workspace/app/Modules');
    }

    public function test_clear_is_idempotent(): void
    {
        $store = new ModuleCacheStore($this->path);
        $store->write($this->manifest());

        self::assertTrue($store->clear());
        self::assertFileDoesNotExist($this->path);
        self::assertFalse($store->clear());
    }

    public function test_metadata_compiler_reuses_a_cached_descriptor(): void
    {
        $cached = new ModuleDescriptor(CacheBetaModule::class, [], []);
        $compiled = (new ModuleMetadataCompiler([$cached]))->compile(CacheBetaModule::class);

        self::assertSame($cached, $compiled);
        self::assertSame([], $compiled->dependencies());
    }

    private function manifest(): ModuleCacheManifest
    {
        $registry = new ModuleRegistry([
            new DiscoveredModule(
                'CacheAlpha',
                CacheAlphaModule::class,
                '/workspace/app/Modules/CacheAlpha/CacheAlphaModule.php',
                __NAMESPACE__,
            ),
            new DiscoveredModule(
                'CacheBeta',
                CacheBetaModule::class,
                '/workspace/app/Modules/CacheBeta/CacheBetaModule.php',
                __NAMESPACE__,
            ),
        ]);

        return new ModuleCacheManifest(
            '/workspace/app/Modules',
            $registry,
            (new ModuleMetadataCompiler)->compileAll($registry->moduleClasses()),
        );
    }
}

final class CacheAlphaModule extends Module
{
}

final class CacheBetaModule extends Module
{
    public function dependencies(): array
    {
        return [CacheAlphaModule::class];
    }

    public function tables(): array
    {
        return ['cache_beta'];
    }
}

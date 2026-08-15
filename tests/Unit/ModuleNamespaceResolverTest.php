<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Exceptions\ModuleGenerationFailed;
use Cluion\Moduark\Generation\ModuleNamespaceResolver;
use Composer\Autoload\ClassLoader;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

final class ModuleNamespaceResolverTest extends TestCase
{
    /**
     * @var list<ClassLoader>
     */
    private array $loaders = [];

    private string $temporaryBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryBasePath = sys_get_temp_dir().'/moduark-namespace-'.bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->temporaryBasePath.'/app', 0755, true));
    }

    protected function tearDown(): void
    {
        foreach ($this->loaders as $loader) {
            $loader->unregister();
        }

        (new Filesystem)->deleteDirectory($this->temporaryBasePath);

        parent::tearDown();
    }

    public function test_it_resolves_a_non_existing_root_from_the_longest_psr4_mapping(): void
    {
        $this->registerLoader('Fixture\\', $this->temporaryBasePath);
        $this->registerLoader('Fixture\\App\\', $this->temporaryBasePath.'/app');

        $namespace = (new ModuleNamespaceResolver)->resolve(
            $this->temporaryBasePath.'/app/Modules',
        );

        self::assertSame('Fixture\\App\\Modules', $namespace);
    }

    public function test_it_rejects_ambiguous_psr4_mappings(): void
    {
        $this->registerLoader('Fixture\\One\\', $this->temporaryBasePath.'/app');
        $this->registerLoader('Fixture\\Two\\', $this->temporaryBasePath.'/app');

        $this->expectException(ModuleGenerationFailed::class);
        $this->expectExceptionMessage(
            'matches multiple Composer PSR-4 namespaces: [Fixture\\One\\Modules], [Fixture\\Two\\Modules].',
        );

        (new ModuleNamespaceResolver)->resolve($this->temporaryBasePath.'/app/Modules');
    }

    public function test_it_rejects_unresolved_parent_segments(): void
    {
        $this->registerLoader('Fixture\\App\\', $this->temporaryBasePath.'/app');
        $path = $this->temporaryBasePath.'/app/missing/../Modules';

        $this->expectException(ModuleGenerationFailed::class);
        $this->expectExceptionMessage(
            "Module path [{$path}] is not inside a registered Composer PSR-4 path.",
        );

        (new ModuleNamespaceResolver)->resolve($path);
    }

    private function registerLoader(string $namespace, string $path): void
    {
        $loader = new ClassLoader($this->temporaryBasePath.'/vendor-'.count($this->loaders));
        $loader->addPsr4($namespace, $path);
        $loader->register(true);
        $this->loaders[] = $loader;
    }
}

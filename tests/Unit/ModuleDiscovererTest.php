<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Discovery\ModuleDiscoverer;
use Cluion\Moduark\Exceptions\ModuleDiscoveryFailed;
use Cluion\Moduark\Registry\ModuleRegistry;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Discovery\Valid\Modules\Alpha\AlphaModule;
use Tests\Fixtures\Discovery\Valid\Modules\Zeta\ZetaModule;

final class ModuleDiscovererTest extends TestCase
{
    private ModuleDiscoverer $discoverer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discoverer = new ModuleDiscoverer;
    }

    public function test_missing_root_is_an_empty_registry(): void
    {
        $registry = $this->discoverer->discover(__DIR__.'/missing-modules');

        self::assertSame([], $registry->all());
        self::assertSame([], $registry->moduleClasses());
    }

    public function test_discovery_is_sorted_and_repeatable(): void
    {
        $root = $this->fixturePath('Valid/Modules');
        $first = $this->discoverer->discover($root);
        $second = $this->discoverer->discover($root);

        self::assertSame([AlphaModule::class, ZetaModule::class], $first->moduleClasses());
        self::assertSame($first->toArray(), $second->toArray());
        self::assertSame(['Alpha', 'Zeta'], array_column($first->toArray(), 'name'));
    }

    public function test_entry_file_must_match_its_module_directory(): void
    {
        $root = $this->fixturePath('InvalidFileName/Modules');
        $path = $root.'/Order/WrongModule.php';

        $this->expectException(ModuleDiscoveryFailed::class);
        $this->expectExceptionMessage(
            "Module entry file [{$path}] must be named [OrderModule.php].",
        );

        $this->discoverer->discover($root);
    }

    public function test_namespace_must_end_with_the_module_name(): void
    {
        $root = $this->fixturePath('InvalidNamespace/Modules');
        $path = $root.'/Order/OrderModule.php';

        $this->expectException(ModuleDiscoveryFailed::class);
        $this->expectExceptionMessage(
            "Module entry file [{$path}] namespace must end with [Order]; found [Inventory].",
        );

        $this->discoverer->discover($root);
    }

    public function test_entry_class_must_be_a_concrete_module(): void
    {
        $root = $this->fixturePath('InvalidType/Modules');
        $path = $root.'/Order/OrderModule.php';
        $moduleClass = 'Tests\\Fixtures\\Discovery\\InvalidType\\Modules\\Order\\OrderModule';

        $this->expectException(ModuleDiscoveryFailed::class);
        $this->expectExceptionMessage(
            "Module entry class [{$moduleClass}] declared in [{$path}] must be a concrete Cluion\\Moduark\\Module.",
        );

        $this->discoverer->discover($root);
    }

    public function test_entry_file_must_declare_a_class(): void
    {
        $root = $this->fixturePath('MissingClass/Modules');
        $path = $root.'/Empty/EmptyModule.php';

        $this->expectException(ModuleDiscoveryFailed::class);
        $this->expectExceptionMessage(
            "Module entry file [{$path}] must declare a named class.",
        );

        $this->discoverer->discover($root);
    }

    public function test_registry_rejects_duplicate_module_names(): void
    {
        $this->expectException(ModuleDiscoveryFailed::class);
        $this->expectExceptionMessage('Duplicate Module name [Alpha] in [/first] and [/second].');

        new ModuleRegistry([
            new DiscoveredModule('Alpha', AlphaModule::class, '/first', 'Example\\Alpha'),
            new DiscoveredModule('Alpha', ZetaModule::class, '/second', 'Example\\Alpha'),
        ]);
    }

    private function fixturePath(string $path): string
    {
        return dirname(__DIR__).'/Fixtures/Discovery/'.$path;
    }
}

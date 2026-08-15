<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Analysis\Source\SourceIndexBuilder;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Exceptions\SourceAnalysisFailed;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Analysis\Modules\Order\OrderModule;
use Tests\Fixtures\Analysis\Modules\User\UserModule;

final class SourceIndexBuilderTest extends TestCase
{
    private ?string $temporaryPath = null;

    protected function tearDown(): void
    {
        if ($this->temporaryPath !== null) {
            (new Filesystem)->deleteDirectory($this->temporaryPath);
        }

        parent::tearDown();
    }

    public function test_it_builds_deterministic_symbol_ownership_and_class_references(): void
    {
        $first = (new SourceIndexBuilder($this->registry()))->build();
        $second = (new SourceIndexBuilder($this->registry()))->build();
        $service = $first->symbol(
            'tests\\fixtures\\analysis\\modules\\user\\services\\userservice',
        );

        self::assertNotNull($service);
        self::assertSame(UserModule::class, $service->owner());
        self::assertSame(
            array_map(static fn ($symbol): array => $symbol->toArray(), $first->symbols()),
            array_map(static fn ($symbol): array => $symbol->toArray(), $second->symbols()),
        );
        self::assertSame(
            array_map(static fn ($reference): array => $reference->toArray(), $first->references()),
            array_map(static fn ($reference): array => $reference->toArray(), $second->references()),
        );

        $crossModuleSymbols = array_values(array_unique(array_map(
            static fn ($reference): string => $reference->symbol(),
            array_filter(
                $first->referencesFrom(OrderModule::class),
                static fn ($reference): bool => $reference->target() === UserModule::class,
            ),
        )));
        sort($crossModuleSymbols, SORT_STRING);

        self::assertSame([
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Attributes\\UserMarker',
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Base\\UserBase',
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Contracts\\SecondContract',
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Contracts\\UserContract',
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Data\\UserData',
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Exceptions\\UserFailure',
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Services\\UserService',
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Support\\UserTrait',
        ], $crossModuleSymbols);
        self::assertNotContains(
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Internal\\UnusedService',
            $crossModuleSymbols,
        );
        self::assertNotEmpty(array_filter(
            $first->referencesFrom(OrderModule::class),
            static fn ($reference): bool => $reference->target() === OrderModule::class,
        ));
    }

    public function test_invalid_php_is_an_analysis_failure_with_source_location(): void
    {
        $this->temporaryPath = sys_get_temp_dir().'/moduark-source-'.bin2hex(random_bytes(6));
        $modulePath = $this->temporaryPath.'/Broken';
        self::assertTrue(mkdir($modulePath, 0755, true));
        self::assertNotFalse(file_put_contents(
            $modulePath.'/BrokenModule.php',
            "<?php\nnamespace Broken;\nfinal class BrokenModule {",
        ));
        $registry = new ModuleRegistry([
            new DiscoveredModule(
                'Broken',
                BrokenSourceModule::class,
                $modulePath.'/BrokenModule.php',
                'Broken',
            ),
        ]);

        $this->expectException(SourceAnalysisFailed::class);
        $this->expectExceptionMessage(
            "Unable to parse Module source [{$modulePath}/BrokenModule.php:3]",
        );

        (new SourceIndexBuilder($registry))->build();
    }

    public function test_duplicate_case_insensitive_symbols_are_rejected(): void
    {
        $this->temporaryPath = sys_get_temp_dir().'/moduark-source-'.bin2hex(random_bytes(6));
        $alphaPath = $this->temporaryPath.'/Alpha/AlphaModule.php';
        $betaPath = $this->temporaryPath.'/Beta/BetaModule.php';
        self::assertTrue(mkdir(dirname($alphaPath), 0755, true));
        self::assertTrue(mkdir(dirname($betaPath), 0755, true));
        self::assertNotFalse(file_put_contents(
            $alphaPath,
            "<?php\nnamespace Duplicate;\nfinal class SharedSymbol {}",
        ));
        self::assertNotFalse(file_put_contents(
            $betaPath,
            "<?php\nnamespace Duplicate;\nfinal class SHAREDSYMBOL {}",
        ));
        $registry = new ModuleRegistry([
            new DiscoveredModule('Beta', DuplicateSourceModule::class, $betaPath, 'Duplicate'),
            new DiscoveredModule('Alpha', BrokenSourceModule::class, $alphaPath, 'Duplicate'),
        ]);

        $this->expectException(SourceAnalysisFailed::class);
        $this->expectExceptionMessage(
            "Module symbol [Duplicate\\SHAREDSYMBOL] is declared by both [{$alphaPath}] and [{$betaPath}].",
        );

        (new SourceIndexBuilder($registry))->build();
    }

    private function registry(): ModuleRegistry
    {
        $root = dirname(__DIR__).'/Fixtures/Analysis/Modules';

        return new ModuleRegistry([
            $this->module('User', UserModule::class, $root),
            $this->module('Order', OrderModule::class, $root),
        ]);
    }

    /**
     * @param class-string<Module> $moduleClass
     */
    private function module(string $name, string $moduleClass, string $root): DiscoveredModule
    {
        return new DiscoveredModule(
            $name,
            $moduleClass,
            "{$root}/{$name}/{$name}Module.php",
            "Tests\\Fixtures\\Analysis\\Modules\\{$name}",
        );
    }
}

final class BrokenSourceModule extends Module
{
}

final class DuplicateSourceModule extends Module
{
}

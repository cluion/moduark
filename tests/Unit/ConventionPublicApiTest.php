<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Analysis\Boundary\ConventionPublicApi;
use Cluion\Moduark\Analysis\Source\SourceIndexBuilder;
use Cluion\Moduark\Analysis\Source\SourceSymbol;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Registry\ModuleRegistry;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Analysis\Modules\User\UserModule;

final class ConventionPublicApiTest extends TestCase
{
    public function test_module_identity_and_convention_directories_are_public(): void
    {
        $module = $this->module();
        $index = (new SourceIndexBuilder(new ModuleRegistry([$module])))->build();
        $publicApi = new ConventionPublicApi;

        foreach ([
            UserModule::class,
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Contracts\\UserContract',
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Data\\UserData',
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Events\\UserCreated',
        ] as $symbolName) {
            $symbol = $index->symbol($symbolName);

            self::assertNotNull($symbol);
            self::assertTrue($publicApi->includes($symbol, $module), $symbolName);
        }
    }

    public function test_other_directories_and_similar_prefixes_are_internal(): void
    {
        $module = $this->module();
        $index = (new SourceIndexBuilder(new ModuleRegistry([$module])))->build();
        $publicApi = new ConventionPublicApi;

        foreach ([
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Attributes\\UserMarker',
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Services\\UserService',
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Support\\UserTrait',
        ] as $symbolName) {
            $symbol = $index->symbol($symbolName);

            self::assertNotNull($symbol);
            self::assertFalse($publicApi->includes($symbol, $module), $symbolName);
        }

        $similarPrefix = new SourceSymbol(
            'Fixture\\AlmostPublic',
            UserModule::class,
            dirname($module->path()).'/ContractsLegacy/AlmostPublic.php',
            1,
        );

        self::assertFalse($publicApi->includes($similarPrefix, $module));
    }

    public function test_windows_style_paths_use_the_same_convention(): void
    {
        $module = $this->module();
        $symbol = new SourceSymbol(
            'Fixture\\WindowsData',
            UserModule::class,
            str_replace('/', '\\', dirname($module->path()).'/Data/WindowsData.php'),
            1,
        );

        self::assertTrue((new ConventionPublicApi)->includes($symbol, $module));
    }

    public function test_nwidart_app_contracts_and_events_are_public_without_broadening_other_directories(): void
    {
        $root = dirname(__DIR__).'/Fixtures/Nwidart/Modules/User/app';

        require_once $root.'/UserModule.php';

        $module = new DiscoveredModule(
            'User',
            'Tests\\Fixtures\\Nwidart\\Modules\\User\\UserModule',
            $root.'/UserModule.php',
            'Tests\\Fixtures\\Nwidart\\Modules\\User',
        );
        $publicApi = new ConventionPublicApi;

        foreach (['Contracts/UserDirectory.php', 'Events/UserCreated.php'] as $path) {
            $symbol = new SourceSymbol(
                'Tests\\Fixtures\\Nwidart\\Modules\\User\\'.str_replace('/', '\\', substr($path, 0, -4)),
                $module->moduleClass(),
                $root.'/'.$path,
                1,
            );

            self::assertTrue($publicApi->includes($symbol, $module), $path);
        }

        $internal = new SourceSymbol(
            'Tests\\Fixtures\\Nwidart\\Modules\\User\\Services\\UserService',
            $module->moduleClass(),
            $root.'/Services/UserService.php',
            1,
        );

        self::assertFalse($publicApi->includes($internal, $module));
    }

    private function module(): DiscoveredModule
    {
        $root = dirname(__DIR__).'/Fixtures/Analysis/Modules/User';

        return new DiscoveredModule(
            'User',
            UserModule::class,
            $root.'/UserModule.php',
            'Tests\\Fixtures\\Analysis\\Modules\\User',
        );
    }
}

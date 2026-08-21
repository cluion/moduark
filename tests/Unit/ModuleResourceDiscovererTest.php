<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Exceptions\ModuleResourceDiscoveryFailed;
use Cluion\Moduark\Resources\ModuleResourceDiscoverer;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Resources\Invalid\InvalidModule;
use Tests\Fixtures\Resources\Shared\SharedModule;
use Workbench\App\Modules\Order\Console\Commands\OrderProbeCommand;
use Workbench\App\Modules\Order\OrderModule;
use Workbench\App\Modules\User\UserModule;

final class ModuleResourceDiscovererTest extends TestCase
{
    public function test_it_discovers_only_existing_conventional_resources(): void
    {
        $rootPath = dirname(__DIR__, 2).'/workbench/app/Modules/Order';
        $resources = (new ModuleResourceDiscoverer)->discover(
            new DiscoveredModule(
                'Order',
                OrderModule::class,
                $rootPath.'/OrderModule.php',
                'Workbench\App\Modules\Order',
            ),
            true,
        );

        self::assertSame('order', $resources->namespace());
        self::assertSame([
            $rootPath.'/routes/web.php',
            $rootPath.'/routes/api.php',
        ], $resources->routePaths());
        self::assertSame($rootPath.'/resources/views', $resources->viewPath());
        self::assertSame($rootPath.'/resources/lang', $resources->translationPath());
        self::assertSame($rootPath.'/Database/Migrations', $resources->migrationPath());
        self::assertSame([OrderProbeCommand::class], $resources->commands());
    }

    public function test_entry_only_module_has_no_resource_side_effects(): void
    {
        $path = dirname(__DIR__, 2).'/workbench/app/Modules/User/UserModule.php';
        $resources = (new ModuleResourceDiscoverer)->discover(
            new DiscoveredModule(
                'User',
                UserModule::class,
                $path,
                'Workbench\App\Modules\User',
            ),
            true,
        );

        self::assertSame([], $resources->routePaths());
        self::assertNull($resources->viewPath());
        self::assertNull($resources->translationPath());
        self::assertNull($resources->migrationPath());
        self::assertSame([], $resources->commands());
    }

    public function test_command_files_are_ignored_outside_console_execution(): void
    {
        $resources = (new ModuleResourceDiscoverer)->discover(
            $this->invalidModule(),
            false,
        );

        self::assertSame([], $resources->commands());
    }

    public function test_it_rejects_a_non_command_php_file_during_console_discovery(): void
    {
        $this->expectException(ModuleResourceDiscoveryFailed::class);
        $this->expectExceptionMessage('must be an autoloadable, instantiable Laravel command');

        (new ModuleResourceDiscoverer)->discover($this->invalidModule(), true);
    }

    public function test_it_ignores_co_located_command_support_types(): void
    {
        $path = dirname(__DIR__).'/Fixtures/Resources/Shared/SharedModule.php';
        $resources = (new ModuleResourceDiscoverer)->discover(
            new DiscoveredModule(
                'Shared',
                SharedModule::class,
                $path,
                'Tests\\Fixtures\\Resources\\Shared',
            ),
            true,
        );

        self::assertSame([], $resources->commands());
    }

    private function invalidModule(): DiscoveredModule
    {
        return new DiscoveredModule(
            'Invalid',
            InvalidModule::class,
            dirname(__DIR__).'/Fixtures/Resources/Invalid/InvalidModule.php',
            'Tests\Fixtures\Resources\Invalid',
        );
    }
}

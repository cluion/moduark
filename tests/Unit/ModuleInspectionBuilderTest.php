<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Analysis\Boundary\ConventionPublicApi;
use Cluion\Moduark\Analysis\Source\SourceIndexBuilder;
use Cluion\Moduark\Architecture\EffectiveArchitecture;
use Cluion\Moduark\Architecture\EffectiveRules;
use Cluion\Moduark\Architecture\Level;
use Cluion\Moduark\Capabilities\CapabilityResolver;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Exceptions\ModuleInspectionFailed;
use Cluion\Moduark\Graph\CapabilityGraphBuilder;
use Cluion\Moduark\Graph\CombinedGraphBuilder;
use Cluion\Moduark\Graph\ModuleGraphBuilder;
use Cluion\Moduark\Inspection\ModuleInspectionBuilder;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Inspection\Modules\Billing\BillingModule;
use Tests\Fixtures\LevelTwo\Capabilities\UserLookup;
use Tests\Fixtures\LevelTwo\Modules\Checkout\CheckoutModule;
use Tests\Fixtures\LevelTwo\Modules\Order\OrderModule;
use Tests\Fixtures\LevelTwo\Modules\User\Contracts\UserFinder;
use Tests\Fixtures\LevelTwo\Modules\User\Providers\UserServiceProvider;
use Tests\Fixtures\LevelTwo\Modules\User\UserModule;

final class ModuleInspectionBuilderTest extends TestCase
{
    public function test_it_builds_a_detailed_level_two_inspection(): void
    {
        $inspection = $this->builder($this->levelTwoModules())->build('order');
        $requirement = $inspection->descriptor()->requires()[0];

        self::assertSame('Order', $inspection->module()->name());
        self::assertSame(Level::Decoupled, $inspection->level());
        self::assertSame([UserModule::class], $inspection->descriptor()->dependencies());
        self::assertSame('User', $inspection->dependencies()[0]->name());
        self::assertTrue($inspection->dependencies()[0]->discovered());
        self::assertSame(UserLookup::class, $requirement->capability());
        self::assertSame('User', $inspection->capabilityProvider(UserLookup::class)->name());
        self::assertSame([], $inspection->missingDependencies());
        self::assertSame(
            [OrderModule::class],
            array_map(static fn ($symbol): string => $symbol->name(), $inspection->publicApi()),
        );
    }

    public function test_it_lists_providers_provided_capabilities_and_convention_public_api(): void
    {
        $inspection = $this->builder($this->levelTwoModules())->build('User');

        self::assertSame([UserServiceProvider::class], $inspection->descriptor()->providers());
        self::assertSame([UserLookup::class], $inspection->descriptor()->provides());
        self::assertSame(
            [UserFinder::class, UserModule::class],
            array_map(static fn ($symbol): string => $symbol->name(), $inspection->publicApi()),
        );
    }

    public function test_it_preserves_missing_direct_dependencies(): void
    {
        $root = dirname(__DIR__).'/Fixtures/Inspection/Modules/Billing';
        $inspection = $this->builder([
            new DiscoveredModule(
                'Billing',
                BillingModule::class,
                $root.'/BillingModule.php',
                'Tests\\Fixtures\\Inspection\\Modules\\Billing',
            ),
        ])->build('Billing');

        self::assertCount(1, $inspection->dependencies());
        self::assertFalse($inspection->dependencies()[0]->discovered());
        self::assertSame(
            $inspection->dependencies(),
            $inspection->missingDependencies(),
        );
    }

    public function test_unknown_module_is_rejected(): void
    {
        $this->expectException(ModuleInspectionFailed::class);
        $this->expectExceptionMessage('Module [Unknown] was not found.');

        $this->builder($this->levelTwoModules())->build('Unknown');
    }

    /** @return list<DiscoveredModule> */
    private function levelTwoModules(): array
    {
        $root = dirname(__DIR__).'/Fixtures/LevelTwo/Modules';

        return [
            $this->module('Order', OrderModule::class, $root),
            $this->module('User', UserModule::class, $root),
            $this->module('Checkout', CheckoutModule::class, $root),
        ];
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
            "Tests\\Fixtures\\LevelTwo\\Modules\\{$name}",
        );
    }

    /** @param list<DiscoveredModule> $modules */
    private function builder(array $modules): ModuleInspectionBuilder
    {
        $registry = new ModuleRegistry($modules);
        $compiler = new ModuleMetadataCompiler;

        return new ModuleInspectionBuilder(
            $registry,
            $compiler,
            new CombinedGraphBuilder(
                new ModuleGraphBuilder($registry, $compiler),
                new CapabilityGraphBuilder($registry, $compiler, new CapabilityResolver),
            ),
            new SourceIndexBuilder($registry),
            new ConventionPublicApi,
            new EffectiveArchitecture(
                Level::Decoupled,
                Level::Decoupled,
                new EffectiveRules([]),
            ),
        );
    }
}

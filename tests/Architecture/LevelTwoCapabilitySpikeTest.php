<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Cluion\Moduark\Analysis\Source\SourceIndex;
use Cluion\Moduark\Analysis\Source\SourceIndexBuilder;
use Cluion\Moduark\Analysis\Source\SourceReference;
use Cluion\Moduark\Capability;
use Cluion\Moduark\CapabilityRequirement;
use Cluion\Moduark\Capabilities\CapabilityPlan;
use Cluion\Moduark\Capabilities\CapabilityResolver;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Exceptions\CapabilityResolutionFailed;
use Cluion\Moduark\Exceptions\InvalidModuleMetadata;
use Cluion\Moduark\Lifecycle\ModuleLifecycleRegistrar;
use Cluion\Moduark\Lifecycle\ModuleOrderer;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\LevelTwo\Capabilities\UserLookup as UserLookupCapability;
use Tests\Fixtures\LevelTwo\Modules\Checkout\Actions\StartCheckout;
use Tests\Fixtures\LevelTwo\Modules\Checkout\Adapters\User\UserLookupAdapter as CheckoutUserLookupAdapter;
use Tests\Fixtures\LevelTwo\Modules\Checkout\CheckoutModule;
use Tests\Fixtures\LevelTwo\Modules\Checkout\Ports\UserLookup as CheckoutUserLookup;
use Tests\Fixtures\LevelTwo\Modules\Order\Actions\PlaceOrder;
use Tests\Fixtures\LevelTwo\Modules\Order\Adapters\User\UserLookupAdapter as OrderUserLookupAdapter;
use Tests\Fixtures\LevelTwo\Modules\Order\OrderModule;
use Tests\Fixtures\LevelTwo\Modules\Order\Ports\UserLookup as OrderUserLookup;
use Tests\Fixtures\LevelTwo\Modules\User\UserModule;

final class LevelTwoCapabilitySpikeTest extends TestCase
{
    public function test_three_modules_resolve_consumer_ports_through_one_capability_provider(): void
    {
        $modules = $this->moduleClasses();
        $resolver = new CapabilityResolver;
        $plan = $resolver->resolve($this->descriptors($modules));
        $application = new Application;
        $registrar = new ModuleLifecycleRegistrar(
            $application,
            new ModuleMetadataCompiler,
            new ModuleOrderer,
        );

        self::assertFalse($application->bound(CheckoutUserLookup::class));
        self::assertFalse($application->bound(OrderUserLookup::class));

        $registrar->registerProviders($modules);

        self::assertTrue($application->bound(CheckoutUserLookup::class));
        self::assertTrue($application->bound(OrderUserLookup::class));

        self::assertSame([
            CheckoutModule::class,
            OrderModule::class,
        ], array_map(
            static fn ($binding): string => $binding->consumer(),
            $plan->bindings(),
        ));
        self::assertSame([
            UserModule::class,
            UserModule::class,
        ], array_map(
            static fn ($binding): string => $binding->provider(),
            $plan->bindings(),
        ));
        self::assertSame([
            UserLookupCapability::class,
            UserLookupCapability::class,
        ], array_map(
            static fn ($binding): string => $binding->capability(),
            $plan->bindings(),
        ));

        self::assertInstanceOf(
            CheckoutUserLookupAdapter::class,
            $application->make(CheckoutUserLookup::class),
        );
        self::assertInstanceOf(
            OrderUserLookupAdapter::class,
            $application->make(OrderUserLookup::class),
        );
        self::assertSame(
            'Checkout for User 42',
            $application->make(StartCheckout::class)->forUser(42),
        );
        self::assertSame(
            'Order for User 42',
            $application->make(PlaceOrder::class)->forUser(42),
        );
    }

    public function test_missing_provider_fails_before_any_container_binding(): void
    {
        $application = new Application;

        try {
            (new CapabilityResolver)->resolve($this->descriptors([
                OrderModule::class,
                CheckoutModule::class,
            ]));
            self::fail('A missing Capability provider must fail resolution.');
        } catch (CapabilityResolutionFailed $exception) {
            self::assertSame(
                'Capability ['.UserLookupCapability::class.'] required by ['
                .CheckoutModule::class.'] has no provider.',
                $exception->getMessage(),
            );
        }

        self::assertFalse($application->bound(CheckoutUserLookup::class));
        self::assertFalse($application->bound(OrderUserLookup::class));
    }

    public function test_ambiguous_provider_diagnostic_is_deterministic(): void
    {
        $first = $this->ambiguousProviderMessage([
            UserModule::class,
            AlternativeUserCapabilityModule::class,
            OrderModule::class,
        ]);
        $second = $this->ambiguousProviderMessage([
            OrderModule::class,
            AlternativeUserCapabilityModule::class,
            UserModule::class,
        ]);

        self::assertSame($first, $second);
        self::assertStringContainsString(UserLookupCapability::class, $first);
        self::assertStringContainsString('has multiple providers', $first);
        self::assertStringContainsString(AlternativeUserCapabilityModule::class, $first);
        self::assertStringContainsString(UserModule::class, $first);
    }

    public function test_adapter_must_implement_the_consumer_owned_port(): void
    {
        $this->expectException(InvalidModuleMetadata::class);
        $this->expectExceptionMessage(
            'Capability Adapter ['.PlaceOrder::class
            .'] must be an instantiable class implementing consumer Port ['
            .OrderUserLookup::class.'].',
        );

        (new ModuleMetadataCompiler)->compile(InvalidAdapterCapabilityModule::class);
    }

    public function test_resolved_metadata_has_a_scalar_config_cache_payload(): void
    {
        $payload = (new CapabilityResolver)
            ->resolve($this->descriptors($this->moduleClasses()))
            ->toArray();
        $cached = unserialize(serialize($payload), ['allowed_classes' => false]);
        $restored = CapabilityPlan::fromArray($payload);

        self::assertSame($payload, $cached);
        self::assertSame($payload, $restored->toArray());

        array_walk_recursive($payload, static function (mixed $value): void {
            self::assertTrue(is_scalar($value) || $value === null);
        });
    }

    public function test_provider_has_no_consumer_reference_and_only_adapters_cross_the_boundary(): void
    {
        $index = (new SourceIndexBuilder($this->registry()))->build();
        $providerReferences = array_filter(
            $index->referencesFrom(UserModule::class),
            static fn (SourceReference $reference): bool => in_array(
                $reference->target(),
                [CheckoutModule::class, OrderModule::class],
                true,
            ),
        );

        self::assertSame([], array_values($providerReferences));
        $this->assertConsumerBoundary($index, CheckoutModule::class);
        $this->assertConsumerBoundary($index, OrderModule::class);
    }

    /**
     * @param list<class-string<Module>> $moduleClasses
     */
    private function ambiguousProviderMessage(array $moduleClasses): string
    {
        try {
            (new CapabilityResolver)->resolve($this->descriptors($moduleClasses));
            self::fail('Ambiguous Capability providers must fail resolution.');
        } catch (CapabilityResolutionFailed $exception) {
            return $exception->getMessage();
        }
    }

    /**
     * @param class-string<Module> $consumer
     */
    private function assertConsumerBoundary(SourceIndex $index, string $consumer): void
    {
        $providerReferences = array_values(array_filter(
            $index->referencesFrom($consumer),
            static fn (SourceReference $reference): bool => $reference->target() === UserModule::class,
        ));
        $coreReferences = array_filter(
            $providerReferences,
            static fn (SourceReference $reference): bool => self::fileIsIn(
                $reference,
                ['/Actions/', '/Ports/'],
            ),
        );
        $adapterReferences = array_filter(
            $providerReferences,
            static fn (SourceReference $reference): bool => self::fileIsIn(
                $reference,
                ['/Adapters/'],
            ),
        );

        self::assertSame([], array_values($coreReferences));
        self::assertNotEmpty($adapterReferences);
    }

    /**
     * @param list<string> $directories
     */
    private static function fileIsIn(SourceReference $reference, array $directories): bool
    {
        $file = str_replace('\\', '/', $reference->file());

        foreach ($directories as $directory) {
            if (str_contains($file, $directory)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<class-string<Module>>
     */
    private function moduleClasses(): array
    {
        return [
            CheckoutModule::class,
            UserModule::class,
            OrderModule::class,
        ];
    }

    /**
     * @param list<class-string<Module>> $moduleClasses
     * @return list<ModuleDescriptor>
     */
    private function descriptors(array $moduleClasses): array
    {
        return (new ModuleMetadataCompiler)->compileAll($moduleClasses);
    }

    private function registry(): ModuleRegistry
    {
        $root = dirname(__DIR__).'/Fixtures/LevelTwo/Modules';

        return new ModuleRegistry([
            $this->discoveredModule('Checkout', CheckoutModule::class, $root),
            $this->discoveredModule('Order', OrderModule::class, $root),
            $this->discoveredModule('User', UserModule::class, $root),
        ]);
    }

    /**
     * @param class-string<Module> $moduleClass
     */
    private function discoveredModule(
        string $name,
        string $moduleClass,
        string $root,
    ): DiscoveredModule {
        return new DiscoveredModule(
            $name,
            $moduleClass,
            "{$root}/{$name}/{$name}Module.php",
            "Tests\\Fixtures\\LevelTwo\\Modules\\{$name}",
        );
    }
}

final class AlternativeUserCapabilityModule extends Module
{
    /** @return list<class-string<Capability>> */
    public function provides(): array
    {
        return [UserLookupCapability::class];
    }

    /** @return list<CapabilityRequirement> */
    public function requires(): array
    {
        return [];
    }
}

final class InvalidAdapterCapabilityModule extends Module
{
    /** @return list<CapabilityRequirement> */
    public function requires(): array
    {
        return [new CapabilityRequirement(
            UserLookupCapability::class,
            OrderUserLookup::class,
            PlaceOrder::class,
        )];
    }
}

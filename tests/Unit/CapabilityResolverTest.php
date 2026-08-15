<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Capabilities\CapabilityPlan;
use Cluion\Moduark\Capabilities\CapabilityResolver;
use Cluion\Moduark\Exceptions\CapabilityResolutionFailed;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Module;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\LevelTwo\Capabilities\UserLookup as UserLookupCapability;
use Tests\Fixtures\LevelTwo\Modules\Checkout\Adapters\User\UserLookupAdapter as CheckoutUserLookupAdapter;
use Tests\Fixtures\LevelTwo\Modules\Checkout\CheckoutModule;
use Tests\Fixtures\LevelTwo\Modules\Checkout\Ports\UserLookup as CheckoutUserLookup;
use Tests\Fixtures\LevelTwo\Modules\Order\Adapters\User\UserLookupAdapter as OrderUserLookupAdapter;
use Tests\Fixtures\LevelTwo\Modules\Order\OrderModule;
use Tests\Fixtures\LevelTwo\Modules\Order\Ports\UserLookup as OrderUserLookup;
use Tests\Fixtures\LevelTwo\Modules\User\UserModule;

final class CapabilityResolverTest extends TestCase
{
    public function test_it_resolves_a_deterministic_binding_plan_from_descriptors(): void
    {
        $first = $this->resolve([
            OrderModule::class,
            UserModule::class,
            CheckoutModule::class,
        ]);
        $second = $this->resolve([
            CheckoutModule::class,
            OrderModule::class,
            UserModule::class,
        ]);

        $expected = [
            [
                'capability' => UserLookupCapability::class,
                'provider' => UserModule::class,
                'consumer' => CheckoutModule::class,
                'port' => CheckoutUserLookup::class,
                'adapter' => CheckoutUserLookupAdapter::class,
            ],
            [
                'capability' => UserLookupCapability::class,
                'provider' => UserModule::class,
                'consumer' => OrderModule::class,
                'port' => OrderUserLookup::class,
                'adapter' => OrderUserLookupAdapter::class,
            ],
        ];

        self::assertSame($expected, $first->toArray());
        self::assertSame($expected, $second->toArray());
    }

    public function test_missing_provider_diagnostic_is_deterministic(): void
    {
        $first = $this->resolutionFailure([
            OrderModule::class,
            CheckoutModule::class,
        ]);
        $second = $this->resolutionFailure([
            CheckoutModule::class,
            OrderModule::class,
        ]);

        self::assertSame($first, $second);
        self::assertSame(
            'Capability ['.UserLookupCapability::class.'] required by ['
            .CheckoutModule::class.'] has no provider.',
            $first,
        );
    }

    public function test_ambiguous_provider_diagnostic_sorts_provider_classes(): void
    {
        $compiler = new ModuleMetadataCompiler;
        $consumer = $compiler->compile(OrderModule::class);
        $provider = $compiler->compile(UserModule::class);
        $alternative = new ModuleDescriptor(
            ResolutionAlternativeUserModule::class,
            [],
            [],
            [],
            [UserLookupCapability::class],
        );
        $providers = [
            UserModule::class,
            ResolutionAlternativeUserModule::class,
        ];
        sort($providers, SORT_STRING);

        $this->expectException(CapabilityResolutionFailed::class);
        $this->expectExceptionMessage(
            'Capability ['.UserLookupCapability::class.'] required by ['
            .OrderModule::class.'] has multiple providers ['
            .implode(', ', $providers).'].',
        );

        (new CapabilityResolver)->resolve([$provider, $consumer, $alternative]);
    }

    public function test_plan_payload_round_trips_without_objects(): void
    {
        $payload = $this->resolve([
            UserModule::class,
            OrderModule::class,
        ])->toArray();
        $cached = unserialize(serialize($payload), ['allowed_classes' => false]);
        $restored = CapabilityPlan::fromArray($payload);

        self::assertSame($payload, $cached);
        self::assertSame($payload, $restored->toArray());

        array_walk_recursive($payload, static function (mixed $value): void {
            self::assertTrue(is_scalar($value) || $value === null);
        });
    }

    public function test_multiple_unused_providers_do_not_require_selection(): void
    {
        $provider = (new ModuleMetadataCompiler)->compile(UserModule::class);
        $alternative = new ModuleDescriptor(
            ResolutionAlternativeUserModule::class,
            [],
            [],
            [],
            [UserLookupCapability::class],
        );

        $plan = (new CapabilityResolver)->resolve([$alternative, $provider]);

        self::assertSame([], $plan->bindings());
    }

    public function test_empty_descriptor_set_produces_an_empty_plan(): void
    {
        $plan = (new CapabilityResolver)->resolve([]);

        self::assertSame([], $plan->bindings());
        self::assertSame([], $plan->toArray());
    }

    /**
     * @param list<class-string<Module>> $moduleClasses
     */
    private function resolve(array $moduleClasses): CapabilityPlan
    {
        $descriptors = array_map(
            static fn (ModuleDescriptor $descriptor): ModuleDescriptor => ModuleDescriptor::fromArray(
                $descriptor->toArray(),
            ),
            (new ModuleMetadataCompiler)->compileAll($moduleClasses),
        );

        return (new CapabilityResolver)->resolve($descriptors);
    }

    /**
     * @param list<class-string<Module>> $moduleClasses
     */
    private function resolutionFailure(array $moduleClasses): string
    {
        try {
            $this->resolve($moduleClasses);
            self::fail('Capability resolution must fail.');
        } catch (CapabilityResolutionFailed $exception) {
            return $exception->getMessage();
        }
    }
}

final class ResolutionAlternativeUserModule extends Module
{
}

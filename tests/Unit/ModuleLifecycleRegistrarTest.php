<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Capability;
use Cluion\Moduark\CapabilityRequirement;
use Cluion\Moduark\Exceptions\CapabilityResolutionFailed;
use Cluion\Moduark\Exceptions\CircularModuleDependency;
use Cluion\Moduark\Exceptions\InvalidModuleMetadata;
use Cluion\Moduark\Lifecycle\ModuleLifecycleRegistrar;
use Cluion\Moduark\Lifecycle\ModuleOrderer;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Module;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ModuleLifecycleRegistrarTest extends TestCase
{
    private Application $application;

    private LifecycleProbe $probe;

    private ModuleLifecycleRegistrar $registrar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->application = new Application;
        $this->probe = new LifecycleProbe;
        $this->application->instance(LifecycleProbe::class, $this->probe);
        $this->registrar = new ModuleLifecycleRegistrar(
            $this->application,
            new ModuleMetadataCompiler,
            new ModuleOrderer,
        );
    }

    public function test_dependencies_register_and_boot_before_consumers(): void
    {
        $ordered = $this->registrar->registerProviders([
            LifecyclePaymentModule::class,
            LifecycleUserModule::class,
            LifecycleOrderModule::class,
        ]);

        self::assertSame([
            LifecycleUserModule::class,
            LifecycleOrderModule::class,
            LifecyclePaymentModule::class,
        ], array_map(
            static fn (ModuleDescriptor $descriptor): string => $descriptor->moduleClass(),
            $ordered,
        ));

        self::assertSame([
            'user.register',
            'order.register',
            'payment.register',
        ], $this->probe->events());

        $this->application->boot();

        self::assertSame([
            'user.register',
            'order.register',
            'payment.register',
            'user.boot',
            'order.boot',
            'payment.boot',
        ], $this->probe->events());
    }

    public function test_cycle_is_rejected_before_any_lifecycle_side_effect(): void
    {
        try {
            $this->registrar->registerProviders([
                LifecycleCycleAlphaModule::class,
                LifecycleCycleBetaModule::class,
                LifecycleCycleGammaModule::class,
            ]);

            self::fail('Expected a circular module dependency exception.');
        } catch (CircularModuleDependency $exception) {
            self::assertSame([
                LifecycleCycleAlphaModule::class,
                LifecycleCycleBetaModule::class,
                LifecycleCycleGammaModule::class,
                LifecycleCycleAlphaModule::class,
            ], $exception->cycle());
        }

        self::assertSame([], $this->probe->events());
    }

    public function test_missing_dependency_is_rejected_before_any_lifecycle_side_effect(): void
    {
        $this->expectException(InvalidModuleMetadata::class);
        $this->expectExceptionMessage(sprintf(
            'Module [%s] depends on missing module [%s].',
            LifecycleOrderModule::class,
            LifecycleUserModule::class,
        ));

        try {
            $this->registrar->registerProviders([LifecycleOrderModule::class]);
        } finally {
            self::assertSame([], $this->probe->events());
        }
    }

    public function test_capability_preflight_preserves_registration_order_and_binds_ports_after_providers(): void
    {
        $ordered = $this->registrar->registerProviders([
            LifecycleCapabilityConsumerModule::class,
            LifecycleCapabilityProviderModule::class,
        ]);

        self::assertSame([
            LifecycleCapabilityProviderModule::class,
            LifecycleCapabilityConsumerModule::class,
        ], array_map(
            static fn (ModuleDescriptor $descriptor): string => $descriptor->moduleClass(),
            $ordered,
        ));
        self::assertSame([
            'user.register',
            'order.register',
        ], $this->probe->events());
        self::assertTrue($this->application->bound(LifecycleUserLookupPort::class));
        self::assertInstanceOf(
            LifecycleUserLookupAdapter::class,
            $this->application->make(LifecycleUserLookupPort::class),
        );
    }

    public function test_missing_capability_provider_is_rejected_before_any_lifecycle_side_effect(): void
    {
        $this->expectException(CapabilityResolutionFailed::class);
        $this->expectExceptionMessage(
            'Capability ['.LifecycleUserLookupCapability::class.'] required by ['
            .LifecycleMissingCapabilityConsumerModule::class.'] has no provider.',
        );

        try {
            $this->registrar->registerProviders([
                LifecycleUserModule::class,
                LifecycleMissingCapabilityConsumerModule::class,
            ]);
        } finally {
            self::assertSame([], $this->probe->events());
            self::assertFalse($this->application->bound(LifecycleUserLookupPort::class));
        }
    }

    public function test_ambiguous_capability_provider_is_rejected_before_any_lifecycle_side_effect(): void
    {
        $this->expectException(CapabilityResolutionFailed::class);
        $this->expectExceptionMessage('has multiple providers');

        try {
            $this->registrar->registerProviders([
                LifecycleCapabilityProviderModule::class,
                LifecycleAlternativeCapabilityProviderModule::class,
                LifecycleMissingCapabilityConsumerModule::class,
            ]);
        } finally {
            self::assertSame([], $this->probe->events());
            self::assertFalse($this->application->bound(LifecycleUserLookupPort::class));
        }
    }

    public function test_duplicate_capability_port_is_rejected_before_any_lifecycle_side_effect(): void
    {
        $this->expectException(CapabilityResolutionFailed::class);
        $this->expectExceptionMessage('is required by multiple consumer Modules');

        try {
            $this->registrar->registerProviders([
                LifecycleCapabilityProviderModule::class,
                LifecycleCapabilityConsumerModule::class,
                LifecycleSecondCapabilityConsumerModule::class,
            ]);
        } finally {
            self::assertSame([], $this->probe->events());
            self::assertFalse($this->application->bound(LifecycleUserLookupPort::class));
        }
    }

    public function test_provider_failure_does_not_apply_capability_bindings(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Capability consumer provider registration failed.');

        try {
            $this->registrar->registerProviders([
                LifecycleCapabilityProviderModule::class,
                LifecycleFailingCapabilityConsumerModule::class,
            ]);
        } finally {
            self::assertSame([
                'user.register',
                'failing.register',
            ], $this->probe->events());
            self::assertFalse($this->application->bound(LifecycleUserLookupPort::class));
        }
    }

    public function test_descriptor_cache_payload_round_trips_without_objects(): void
    {
        $descriptor = (new ModuleMetadataCompiler)->compile(LifecycleOrderModule::class);
        $payload = $descriptor->toArray();

        $cached = unserialize(serialize($payload), ['allowed_classes' => false]);

        self::assertSame($payload, $cached);

        $restored = ModuleDescriptor::fromArray($payload);

        self::assertSame($payload, $restored->toArray());

        array_walk_recursive($payload, static function (mixed $value): void {
            self::assertTrue(is_scalar($value) || $value === null);
        });
    }
}

final class LifecycleProbe
{
    /**
     * @var list<string>
     */
    private array $events = [];

    public function record(string $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @return list<string>
     */
    public function events(): array
    {
        return $this->events;
    }
}

abstract class RecordingServiceProvider extends ServiceProvider
{
    abstract protected function moduleName(): string;

    public function register(): void
    {
        $this->probe()->record($this->moduleName().'.register');
    }

    public function boot(): void
    {
        $this->probe()->record($this->moduleName().'.boot');
    }

    private function probe(): LifecycleProbe
    {
        $probe = $this->app->make(LifecycleProbe::class);

        return $probe;
    }
}

final class LifecycleUserServiceProvider extends RecordingServiceProvider
{
    protected function moduleName(): string
    {
        return 'user';
    }
}

final class LifecycleOrderServiceProvider extends RecordingServiceProvider
{
    protected function moduleName(): string
    {
        return 'order';
    }
}

final class LifecyclePaymentServiceProvider extends RecordingServiceProvider
{
    protected function moduleName(): string
    {
        return 'payment';
    }
}

final class LifecycleFailingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(LifecycleProbe::class)->record('failing.register');

        throw new RuntimeException('Capability consumer provider registration failed.');
    }
}

final class LifecycleUserModule extends Module
{
    /**
     * @return list<class-string<ServiceProvider>>
     */
    public function providers(): array
    {
        return [LifecycleUserServiceProvider::class];
    }
}

final class LifecycleOrderModule extends Module
{
    /**
     * @return list<class-string<Module>>
     */
    public function dependencies(): array
    {
        return [LifecycleUserModule::class];
    }

    /**
     * @return list<class-string<ServiceProvider>>
     */
    public function providers(): array
    {
        return [LifecycleOrderServiceProvider::class];
    }
}

final class LifecyclePaymentModule extends Module
{
    /**
     * @return list<class-string<Module>>
     */
    public function dependencies(): array
    {
        return [LifecycleOrderModule::class];
    }

    /**
     * @return list<class-string<ServiceProvider>>
     */
    public function providers(): array
    {
        return [LifecyclePaymentServiceProvider::class];
    }
}

final class LifecycleCycleAlphaModule extends Module
{
    /**
     * @return list<class-string<Module>>
     */
    public function dependencies(): array
    {
        return [LifecycleCycleBetaModule::class];
    }

    /**
     * @return list<class-string<ServiceProvider>>
     */
    public function providers(): array
    {
        return [LifecycleUserServiceProvider::class];
    }
}

final class LifecycleCycleBetaModule extends Module
{
    /**
     * @return list<class-string<Module>>
     */
    public function dependencies(): array
    {
        return [LifecycleCycleGammaModule::class];
    }

    /**
     * @return list<class-string<ServiceProvider>>
     */
    public function providers(): array
    {
        return [LifecycleOrderServiceProvider::class];
    }
}

final class LifecycleCycleGammaModule extends Module
{
    /**
     * @return list<class-string<Module>>
     */
    public function dependencies(): array
    {
        return [LifecycleCycleAlphaModule::class];
    }

    /**
     * @return list<class-string<ServiceProvider>>
     */
    public function providers(): array
    {
        return [LifecyclePaymentServiceProvider::class];
    }
}

interface LifecycleUserLookupCapability extends Capability
{
}

interface LifecycleUserLookupPort
{
    public function name(int $userId): string;
}

final class LifecycleUserLookupAdapter implements LifecycleUserLookupPort
{
    public function name(int $userId): string
    {
        return "User {$userId}";
    }
}

final class LifecycleCapabilityProviderModule extends Module
{
    /** @return list<class-string<ServiceProvider>> */
    public function providers(): array
    {
        return [LifecycleUserServiceProvider::class];
    }

    /** @return list<class-string<Capability>> */
    public function provides(): array
    {
        return [LifecycleUserLookupCapability::class];
    }
}

final class LifecycleAlternativeCapabilityProviderModule extends Module
{
    /** @return list<class-string<ServiceProvider>> */
    public function providers(): array
    {
        return [LifecyclePaymentServiceProvider::class];
    }

    /** @return list<class-string<Capability>> */
    public function provides(): array
    {
        return [LifecycleUserLookupCapability::class];
    }
}

final class LifecycleCapabilityConsumerModule extends Module
{
    /** @return list<class-string<Module>> */
    public function dependencies(): array
    {
        return [LifecycleCapabilityProviderModule::class];
    }

    /** @return list<class-string<ServiceProvider>> */
    public function providers(): array
    {
        return [LifecycleOrderServiceProvider::class];
    }

    /** @return list<CapabilityRequirement> */
    public function requires(): array
    {
        return [new CapabilityRequirement(
            LifecycleUserLookupCapability::class,
            LifecycleUserLookupPort::class,
            LifecycleUserLookupAdapter::class,
        )];
    }
}

final class LifecycleFailingCapabilityConsumerModule extends Module
{
    /** @return list<class-string<Module>> */
    public function dependencies(): array
    {
        return [LifecycleCapabilityProviderModule::class];
    }

    /** @return list<class-string<ServiceProvider>> */
    public function providers(): array
    {
        return [LifecycleFailingServiceProvider::class];
    }

    /** @return list<CapabilityRequirement> */
    public function requires(): array
    {
        return [new CapabilityRequirement(
            LifecycleUserLookupCapability::class,
            LifecycleUserLookupPort::class,
            LifecycleUserLookupAdapter::class,
        )];
    }
}

final class LifecycleSecondCapabilityConsumerModule extends Module
{
    /** @return list<class-string<Module>> */
    public function dependencies(): array
    {
        return [LifecycleCapabilityProviderModule::class];
    }

    /** @return list<class-string<ServiceProvider>> */
    public function providers(): array
    {
        return [LifecyclePaymentServiceProvider::class];
    }

    /** @return list<CapabilityRequirement> */
    public function requires(): array
    {
        return [new CapabilityRequirement(
            LifecycleUserLookupCapability::class,
            LifecycleUserLookupPort::class,
            LifecycleUserLookupAdapter::class,
        )];
    }
}

final class LifecycleMissingCapabilityConsumerModule extends Module
{
    /** @return list<class-string<ServiceProvider>> */
    public function providers(): array
    {
        return [LifecycleOrderServiceProvider::class];
    }

    /** @return list<CapabilityRequirement> */
    public function requires(): array
    {
        return [new CapabilityRequirement(
            LifecycleUserLookupCapability::class,
            LifecycleUserLookupPort::class,
            LifecycleUserLookupAdapter::class,
        )];
    }
}

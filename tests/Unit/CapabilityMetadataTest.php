<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Capability;
use Cluion\Moduark\CapabilityRequirement;
use Cluion\Moduark\Exceptions\InvalidModuleMetadata;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Module;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\LevelTwo\Capabilities\UserLookup as UserLookupCapability;
use Tests\Fixtures\LevelTwo\Modules\Order\Adapters\User\UserLookupAdapter as OrderUserLookupAdapter;
use Tests\Fixtures\LevelTwo\Modules\Order\OrderModule;
use Tests\Fixtures\LevelTwo\Modules\Order\Ports\UserLookup as OrderUserLookup;
use Tests\Fixtures\LevelTwo\Modules\User\UserModule;

final class CapabilityMetadataTest extends TestCase
{
    public function test_empty_modules_and_legacy_descriptor_payloads_remain_compatible(): void
    {
        $descriptor = (new ModuleMetadataCompiler)->compile(EmptyCapabilityModule::class);

        self::assertSame([], $descriptor->requires());
        self::assertSame([], $descriptor->provides());
        self::assertSame([
            'module' => EmptyCapabilityModule::class,
            'dependencies' => [],
            'providers' => [],
            'requires' => [],
            'provides' => [],
        ], $descriptor->toArray());

        $legacy = ModuleDescriptor::fromArray([
            'module' => EmptyCapabilityModule::class,
            'dependencies' => [],
            'providers' => [],
        ]);

        self::assertSame([], $legacy->requires());
        self::assertSame([], $legacy->provides());
    }

    public function test_compiler_produces_typed_requirements_and_scalar_cache_payloads(): void
    {
        $compiler = new ModuleMetadataCompiler;
        $provider = $compiler->compile(UserModule::class);
        $consumer = $compiler->compile(OrderModule::class);
        $requirements = $consumer->requires();

        self::assertSame([UserLookupCapability::class], $provider->provides());
        self::assertCount(1, $requirements);
        self::assertSame([
            'capability' => UserLookupCapability::class,
            'port' => OrderUserLookup::class,
            'adapter' => OrderUserLookupAdapter::class,
        ], $requirements[0]->toArray());

        $payload = $consumer->toArray();
        $cached = unserialize(serialize($payload), ['allowed_classes' => false]);
        $restored = ModuleDescriptor::fromArray($payload);

        self::assertSame($payload, $cached);
        self::assertSame($payload, $restored->toArray());

        array_walk_recursive($payload, static function (mixed $value): void {
            self::assertTrue(is_scalar($value) || $value === null);
        });
    }

    public function test_base_capability_marker_is_not_a_capability_identity(): void
    {
        $this->expectException(InvalidModuleMetadata::class);
        $this->expectExceptionMessage(
            BaseCapabilityProviderModule::class.'::provides() must return '
            .'class-string extending '.Capability::class.' entries; received string.',
        );

        (new ModuleMetadataCompiler)->compile(BaseCapabilityProviderModule::class);
    }

    public function test_duplicate_provided_capability_is_rejected(): void
    {
        $this->expectException(InvalidModuleMetadata::class);
        $this->expectExceptionMessage(
            DuplicateCapabilityProviderModule::class.'::provides() contains duplicate reference ['
            .UserLookupCapability::class.'].',
        );

        (new ModuleMetadataCompiler)->compile(DuplicateCapabilityProviderModule::class);
    }

    public function test_duplicate_required_capability_is_rejected(): void
    {
        $this->expectException(InvalidModuleMetadata::class);
        $this->expectExceptionMessage(
            DuplicateCapabilityConsumerModule::class.'::requires() contains duplicate reference ['
            .UserLookupCapability::class.'].',
        );

        (new ModuleMetadataCompiler)->compile(DuplicateCapabilityConsumerModule::class);
    }

    public function test_one_consumer_port_cannot_be_bound_by_two_capabilities(): void
    {
        $this->expectException(InvalidModuleMetadata::class);
        $this->expectExceptionMessage(
            DuplicateCapabilityPortModule::class.'::requires() contains duplicate Port ['
            .OrderUserLookup::class.'].',
        );

        (new ModuleMetadataCompiler)->compile(DuplicateCapabilityPortModule::class);
    }

    public function test_capability_port_must_be_an_interface(): void
    {
        $this->expectException(InvalidModuleMetadata::class);
        $this->expectExceptionMessage(
            InvalidCapabilityPortModule::class.'::requires() Port ['
            .OrderUserLookupAdapter::class.'] must be an interface class-string.',
        );

        (new ModuleMetadataCompiler)->compile(InvalidCapabilityPortModule::class);
    }
}

final class EmptyCapabilityModule extends Module
{
}

final class BaseCapabilityProviderModule extends Module
{
    /** @return list<class-string<Capability>> */
    public function provides(): array
    {
        return [Capability::class];
    }
}

final class DuplicateCapabilityProviderModule extends Module
{
    /** @return list<class-string<Capability>> */
    public function provides(): array
    {
        return [UserLookupCapability::class, UserLookupCapability::class];
    }
}

final class DuplicateCapabilityConsumerModule extends Module
{
    /** @return list<CapabilityRequirement> */
    public function requires(): array
    {
        return [
            new CapabilityRequirement(
                UserLookupCapability::class,
                OrderUserLookup::class,
                OrderUserLookupAdapter::class,
            ),
            new CapabilityRequirement(
                UserLookupCapability::class,
                SecondaryUserLookup::class,
                SecondaryUserLookupAdapter::class,
            ),
        ];
    }
}

interface ProductLookupCapability extends Capability
{
}

final class DuplicateCapabilityPortModule extends Module
{
    /** @return list<CapabilityRequirement> */
    public function requires(): array
    {
        return [
            new CapabilityRequirement(
                UserLookupCapability::class,
                OrderUserLookup::class,
                OrderUserLookupAdapter::class,
            ),
            new CapabilityRequirement(
                ProductLookupCapability::class,
                OrderUserLookup::class,
                OrderUserLookupAdapter::class,
            ),
        ];
    }
}

final class InvalidCapabilityPortModule extends Module
{
    /** @return list<CapabilityRequirement> */
    public function requires(): array
    {
        return [new CapabilityRequirement(
            UserLookupCapability::class,
            OrderUserLookupAdapter::class,
            OrderUserLookupAdapter::class,
        )];
    }
}

interface SecondaryUserLookup
{
    public function label(int $userId): string;
}

final class SecondaryUserLookupAdapter implements SecondaryUserLookup
{
    public function label(int $userId): string
    {
        return "User {$userId}";
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Generation\GenerationOptions;
use Cluion\Moduark\Generation\GenerationPlan;
use Cluion\Moduark\Generation\GeneratorDescriptor;
use Cluion\Moduark\Generation\GeneratorRegistration;
use Cluion\Moduark\Generation\GeneratorRegistry;
use Cluion\Moduark\Generation\ModuleMakerTarget;
use Cluion\Moduark\Generation\ModuleMakerType;
use Illuminate\Container\Container;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

final class GeneratorRegistrationTest extends TestCase
{
    public function test_registration_is_independent_of_provider_and_resolution_order(): void
    {
        $early = new Container;
        GeneratorRegistration::register($early, new RegistrationFixtureDescriptor('zeta'));
        $early->singleton(GeneratorRegistry::class);
        $this->registerBuiltIns($early);
        GeneratorRegistration::register($early, RegistrationFixtureDescriptor::class);

        $late = new Container;
        $late->singleton(GeneratorRegistry::class);
        $this->registerBuiltIns($late);
        $late->make(GeneratorRegistry::class);
        GeneratorRegistration::register($late, RegistrationFixtureDescriptor::class);
        GeneratorRegistration::register($late, new RegistrationFixtureDescriptor('zeta'));

        self::assertSame($this->ids($early), $this->ids($late));
        self::assertSame(
            ['cast', 'channel', 'class', 'component', 'controller', 'enum', 'event', 'exception', 'factory', 'interface', 'job', 'job-middleware', 'listener', 'mail', 'middleware', 'migration', 'model', 'notification', 'observer', 'policy', 'request', 'resource', 'rule', 'scope', 'seeder', 'test', 'trait', 'verification', 'view', 'zeta'],
            $this->ids($early),
        );
    }

    public function test_it_rejects_a_class_that_is_not_a_descriptor(): void
    {
        $container = new Container;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Generator descriptor [stdClass] must implement '.GeneratorDescriptor::class.'.',
        );

        GeneratorRegistration::register($container, stdClass::class);
    }

    private function registerBuiltIns(Container $container): void
    {
        foreach (ModuleMakerType::cases() as $descriptor) {
            GeneratorRegistration::register($container, $descriptor);
        }
    }

    /** @return list<string> */
    private function ids(Container $container): array
    {
        return array_map(
            static fn (GeneratorDescriptor $descriptor): string => $descriptor->id(),
            $container->make(GeneratorRegistry::class)->all(),
        );
    }
}

final readonly class RegistrationFixtureDescriptor implements GeneratorDescriptor
{
    public function __construct(private string $id = 'verification')
    {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function targetNamespace(): string
    {
        return 'Verification';
    }

    public function supportedOptions(): array
    {
        return ['force'];
    }

    public function plan(
        ModuleMakerTarget $target,
        GenerationOptions $options,
    ): GenerationPlan {
        return new GenerationPlan([]);
    }
}

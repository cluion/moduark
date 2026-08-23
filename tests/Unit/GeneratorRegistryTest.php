<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Exceptions\ModuleMakerFailed;
use Cluion\Moduark\Generation\GenerationOptions;
use Cluion\Moduark\Generation\GenerationPlan;
use Cluion\Moduark\Generation\GeneratorDescriptor;
use Cluion\Moduark\Generation\GeneratorRegistry;
use Cluion\Moduark\Generation\ModuleMakerTarget;
use Cluion\Moduark\Generation\ModuleMakerType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GeneratorRegistryTest extends TestCase
{
    public function test_it_resolves_case_insensitively_and_lists_canonical_ids_in_order(): void
    {
        $registry = new GeneratorRegistry(ModuleMakerType::cases());

        self::assertSame(ModuleMakerType::Model, $registry->resolve('MODEL'));
        self::assertSame(
            ['cast', 'channel', 'class', 'command', 'component', 'config', 'controller', 'enum', 'event', 'exception', 'factory', 'interface', 'job', 'job-middleware', 'listener', 'mail', 'middleware', 'migration', 'model', 'notification', 'observer', 'policy', 'provider', 'request', 'resource', 'rule', 'scope', 'seeder', 'test', 'trait', 'view'],
            array_map(
                static fn (GeneratorDescriptor $descriptor): string => $descriptor->id(),
                $registry->all(),
            ),
        );
    }

    public function test_it_rejects_duplicate_generator_ids(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Generator ID [model] is already registered.');

        new GeneratorRegistry([ModuleMakerType::Model, ModuleMakerType::Model]);
    }

    public function test_it_rejects_non_canonical_generator_ids(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Generator ID [Bad_ID] must be canonical lowercase kebab-case.',
        );

        new GeneratorRegistry([$this->descriptor('Bad_ID')]);
    }

    public function test_third_party_descriptors_cannot_claim_built_in_ids(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Generator ID [model] is reserved for a built-in descriptor.',
        );

        new GeneratorRegistry([$this->descriptor('model')]);
    }

    public function test_it_rejects_unknown_supported_options(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Generator [value-object] declares unknown supported option [typo].',
        );

        new GeneratorRegistry([$this->descriptor('value-object', ['force', 'typo'])]);
    }

    public function test_it_rejects_duplicate_supported_options(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Generator [value-object] must not declare duplicate supported options.',
        );

        new GeneratorRegistry([$this->descriptor('value-object', ['force', 'force'])]);
    }

    public function test_unknown_ids_preserve_the_public_maker_error(): void
    {
        $registry = new GeneratorRegistry(ModuleMakerType::cases());

        $this->expectException(ModuleMakerFailed::class);
        $this->expectExceptionMessage(
            'Maker type [verification] is not supported; expected cast, channel, class, command, component, config, controller, enum, event, exception, factory, interface, job, job-middleware, listener, mail, middleware, migration, model, notification, observer, policy, provider, request, resource, rule, scope, seeder, test, trait, or view.',
        );

        $registry->resolve('verification');
    }

    /** @param list<string> $supportedOptions */
    private function descriptor(string $id, array $supportedOptions = ['force']): GeneratorDescriptor
    {
        return new class($id, $supportedOptions) implements GeneratorDescriptor
        {
            /** @param list<string> $supportedOptions */
            public function __construct(
                private string $id,
                private array $supportedOptions,
            ) {}

            public function id(): string
            {
                return $this->id;
            }

            public function targetNamespace(): string
            {
                return 'Tests';
            }

            public function supportedOptions(): array
            {
                return $this->supportedOptions;
            }

            public function plan(
                ModuleMakerTarget $target,
                GenerationOptions $options,
            ): GenerationPlan {
                return new GenerationPlan([]);
            }
        };
    }
}

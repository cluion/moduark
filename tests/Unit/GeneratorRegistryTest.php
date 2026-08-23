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
            ['cast', 'class', 'controller', 'enum', 'exception', 'interface', 'middleware', 'model', 'request', 'resource', 'scope', 'trait'],
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

    public function test_unknown_ids_preserve_the_public_maker_error(): void
    {
        $registry = new GeneratorRegistry(ModuleMakerType::cases());

        $this->expectException(ModuleMakerFailed::class);
        $this->expectExceptionMessage(
            'Maker type [rule] is not supported; expected cast, class, controller, enum, exception, interface, middleware, model, request, resource, scope, or trait.',
        );

        $registry->resolve('rule');
    }

    private function descriptor(string $id): GeneratorDescriptor
    {
        return new readonly class($id) implements GeneratorDescriptor
        {
            public function __construct(private string $id)
            {
            }

            public function id(): string
            {
                return $this->id;
            }

            public function targetNamespace(): string
            {
                return 'Tests';
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

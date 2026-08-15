<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Attribute;
use Cluion\Moduark\Module;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class MetadataCandidateTest extends TestCase
{
    public function test_all_candidates_can_express_the_same_dependency(): void
    {
        $methodDependencies = (new MethodCandidateOrderModule)->dependencies();
        $propertyDependencies = (new PropertyCandidateOrderModule)->dependencies();

        $attributes = (new ReflectionClass(AttributeCandidateOrderModule::class))
            ->getAttributes(ModuleMetadataAttribute::class);

        self::assertCount(1, $attributes);

        $attributeDependencies = $attributes[0]->newInstance()->dependencies;

        self::assertSame([MetadataCandidateUserModule::class], $methodDependencies);
        self::assertSame($methodDependencies, $propertyDependencies);
        self::assertSame($methodDependencies, $attributeDependencies);
    }
}

final class MetadataCandidateUserModule extends Module
{
}

final class MethodCandidateOrderModule extends Module
{
    /**
     * @return list<class-string<Module>>
     */
    public function dependencies(): array
    {
        return [MetadataCandidateUserModule::class];
    }
}

abstract class PropertyMetadataCandidate
{
    /**
     * @var list<class-string<Module>>
     */
    protected array $moduleDependencies = [];

    /**
     * @return list<class-string<Module>>
     */
    final public function dependencies(): array
    {
        return $this->moduleDependencies;
    }
}

final class PropertyCandidateOrderModule extends PropertyMetadataCandidate
{
    /**
     * @var list<class-string<Module>>
     */
    protected array $moduleDependencies = [MetadataCandidateUserModule::class];
}

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ModuleMetadataAttribute
{
    /**
     * @param list<class-string<Module>> $dependencies
     */
    public function __construct(public array $dependencies = [])
    {
    }
}

#[ModuleMetadataAttribute(dependencies: [MetadataCandidateUserModule::class])]
final class AttributeCandidateOrderModule extends Module
{
}

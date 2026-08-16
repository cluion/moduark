<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\Rules\CrossModuleModelAccessRule;
use Cluion\Moduark\Analysis\Source\SourceIndex;
use Cluion\Moduark\Analysis\Source\SourceIndexBuilder;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\LevelThree\Modules\Order\OrderModule;
use Tests\Fixtures\LevelThree\Modules\User\UserModule;

final class CrossModuleModelAccessRuleTest extends TestCase
{
    public function test_direct_and_indirect_eloquent_models_are_classified_from_resolved_ast_parents(): void
    {
        $index = $this->sourceIndex();

        self::assertTrue($index->isEloquentModel(
            'Tests\Fixtures\LevelThree\Modules\User\Models\BaseModel',
        ));
        self::assertTrue($index->isEloquentModel(
            'Tests\Fixtures\LevelThree\Modules\User\Models\User',
        ));
        self::assertTrue($index->isEloquentModel(
            'Tests\Fixtures\LevelThree\Modules\Order\Models\Order',
        ));
        self::assertFalse($index->isEloquentModel(
            'Tests\Fixtures\LevelThree\Modules\User\Data\UserData',
        ));
        self::assertFalse($index->isEloquentModel('Missing\Model'));
    }

    public function test_cross_module_model_references_are_reported_without_flagging_other_symbols(): void
    {
        $result = (new CrossModuleModelAccessRule)->inspect(
            $this->context(),
            RuleId::CrossModuleModelAccess->defaultSeverity(),
        );

        self::assertFalse($result->passed());
        self::assertCount(5, $result->violations());
        self::assertSame([
            'rule' => 'cross_module_model_access',
            'code' => 'MOD-MODEL-001',
            'severity' => 'error',
            'message' => 'Module [Order] directly accesses an Eloquent Model owned by Module [User].',
            'file' => dirname(__DIR__).'/Fixtures/LevelThree/Modules/Order/Actions/AccessUser.php',
            'line' => 13,
            'consumer' => 'Order',
            'target' => 'User',
            'symbol' => 'Tests\Fixtures\LevelThree\Modules\User\Models\User',
            'suggestion' => 'Keep only the User identifier and access its data through a Port or exported boundary.',
        ], $result->violations()[0]->toArray());
        self::assertSame(
            [13, 19, 22, 24, 29],
            array_map(static fn ($violation): ?int => $violation->line(), $result->violations()),
        );
        self::assertSame(
            ['Tests\Fixtures\LevelThree\Modules\User\Models\User'],
            array_values(array_unique(array_map(
                static fn ($violation): ?string => $violation->symbol(),
                $result->violations(),
            ))),
        );
    }

    private function context(): AnalysisContext
    {
        $registry = $this->registry();

        return new AnalysisContext($registry, [
            new ModuleDescriptor(OrderModule::class, [UserModule::class], []),
            new ModuleDescriptor(UserModule::class, [], []),
        ], (new SourceIndexBuilder($registry))->build());
    }

    private function sourceIndex(): SourceIndex
    {
        return (new SourceIndexBuilder($this->registry()))->build();
    }

    private function registry(): ModuleRegistry
    {
        $root = dirname(__DIR__).'/Fixtures/LevelThree/Modules';

        return new ModuleRegistry([
            $this->module('User', UserModule::class, $root),
            $this->module('Order', OrderModule::class, $root),
        ]);
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
            "Tests\\Fixtures\\LevelThree\\Modules\\{$name}",
        );
    }
}

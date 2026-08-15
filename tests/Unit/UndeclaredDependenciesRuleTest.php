<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\Rules\UndeclaredDependenciesRule;
use Cluion\Moduark\Analysis\Source\SourceIndexBuilder;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Analysis\Modules\Order\OrderModule;
use Tests\Fixtures\Analysis\Modules\User\UserModule;

final class UndeclaredDependenciesRuleTest extends TestCase
{
    public function test_cross_module_references_without_a_declaration_are_reported(): void
    {
        $result = (new UndeclaredDependenciesRule)->inspect(
            $this->context([]),
            RuleId::UndeclaredDependencies->defaultSeverity(),
        );

        self::assertFalse($result->passed());
        self::assertGreaterThan(8, count($result->violations()));
        self::assertSame([
            'rule' => 'undeclared_dependencies',
            'code' => 'MOD-DEPENDENCY-002',
            'severity' => 'error',
            'message' => 'Module [Order] uses [User] without declaring the dependency.',
            'file' => dirname(__DIR__).'/Fixtures/Analysis/Modules/Order/Actions/ObservedReferences.php',
            'line' => 15,
            'consumer' => 'Order',
            'target' => 'User',
            'symbol' => 'Tests\\Fixtures\\Analysis\\Modules\\User\\Attributes\\UserMarker',
            'suggestion' => 'Declare Module [User] in OrderModule::dependencies() or remove the cross-Module reference.',
        ], $result->violations()[0]->toArray());
        self::assertSame([], array_filter(
            $result->violations(),
            static fn ($violation): bool => $violation->symbol()
                === 'Tests\\Fixtures\\Analysis\\Modules\\User\\Internal\\UnusedService',
        ));
    }

    public function test_declared_and_same_module_references_are_allowed(): void
    {
        $result = (new UndeclaredDependenciesRule)->inspect(
            $this->context([UserModule::class]),
            RuleId::UndeclaredDependencies->defaultSeverity(),
        );

        self::assertTrue($result->passed());
    }

    /**
     * @param list<class-string<Module>> $orderDependencies
     */
    private function context(array $orderDependencies): AnalysisContext
    {
        $root = dirname(__DIR__).'/Fixtures/Analysis/Modules';
        $registry = new ModuleRegistry([
            $this->module('User', UserModule::class, $root),
            $this->module('Order', OrderModule::class, $root),
        ]);

        return new AnalysisContext($registry, [
            new ModuleDescriptor(OrderModule::class, $orderDependencies, []),
            new ModuleDescriptor(UserModule::class, [], []),
        ], (new SourceIndexBuilder($registry))->build());
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
            "Tests\\Fixtures\\Analysis\\Modules\\{$name}",
        );
    }
}

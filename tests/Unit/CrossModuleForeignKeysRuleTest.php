<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\Rules\CrossModuleForeignKeysRule;
use Cluion\Moduark\Analysis\Source\ForeignKeyReference;
use Cluion\Moduark\Analysis\Source\SourceIndex;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use PHPUnit\Framework\TestCase;

final class CrossModuleForeignKeysRuleTest extends TestCase
{
    public function test_cross_module_unresolved_and_unowned_foreign_keys_are_classified(): void
    {
        $result = (new CrossModuleForeignKeysRule)->inspect(
            $this->context(),
            RuleId::CrossModuleForeignKeys->defaultSeverity(),
        );

        self::assertFalse($result->passed());
        self::assertFalse($result->hasErrors());
        self::assertTrue($result->hasWarnings());
        self::assertCount(3, $result->violations());

        $violations = [];

        foreach ($result->violations() as $violation) {
            $violations[$violation->code()] = $violation;
        }

        self::assertSame([
            'rule' => 'cross_module_foreign_keys',
            'code' => 'MOD-FK-001',
            'severity' => 'warning',
            'message' => 'Table [orders] owned by Module [Order] defines a foreign key to table [users] owned by Module [User].',
            'file' => '/modules/Order/Database/Migrations/2026_08_16_000000_orders.php',
            'line' => 20,
            'consumer' => 'Order',
            'target' => 'User',
            'symbol' => 'orders -> users',
            'suggestion' => 'Review the extraction coupling; keep it with a narrow suppression or validate the identifier through a Port or workflow.',
        ], $violations['MOD-FK-001']->toArray());
        self::assertSame(Severity::Warning, $violations['MOD-FK-002']->severity());
        self::assertSame('orders -> Domain\\UserModel::class', $violations['MOD-FK-002']->symbol());
        self::assertSame('ORDERS -> audit_logs', $violations['MOD-FK-003']->symbol());
    }

    public function test_unresolved_evidence_remains_a_warning_when_the_rule_is_elevated(): void
    {
        $result = (new CrossModuleForeignKeysRule)->inspect(
            $this->context(),
            Severity::Error,
        );
        $severities = [];

        foreach ($result->violations() as $violation) {
            $severities[$violation->code()] = $violation->severity();
        }

        self::assertSame(Severity::Error, $severities['MOD-FK-001']);
        self::assertSame(Severity::Warning, $severities['MOD-FK-002']);
        self::assertSame(Severity::Error, $severities['MOD-FK-003']);
    }

    private function context(): AnalysisContext
    {
        $registry = new ModuleRegistry([
            $this->module('User', ForeignKeyUserModule::class),
            $this->module('Order', ForeignKeyOrderModule::class),
        ]);
        $file = '/modules/Order/Database/Migrations/2026_08_16_000000_orders.php';
        $references = [
            new ForeignKeyReference(
                ForeignKeyOrderModule::class,
                'orders',
                null,
                'order_items',
                null,
                'Blueprint::foreignId()->constrained',
                $file,
                10,
            ),
            new ForeignKeyReference(
                ForeignKeyOrderModule::class,
                'orders',
                null,
                'users',
                null,
                'Blueprint::foreign()->on',
                $file,
                20,
            ),
            new ForeignKeyReference(
                ForeignKeyOrderModule::class,
                'ORDERS',
                null,
                'audit_logs',
                null,
                'Blueprint::foreignId()->constrained',
                $file,
                30,
            ),
            new ForeignKeyReference(
                ForeignKeyOrderModule::class,
                'orders',
                null,
                null,
                'Domain\\UserModel::class',
                'Blueprint::foreignIdFor()->constrained',
                $file,
                40,
            ),
        ];

        return new AnalysisContext($registry, [
            new ModuleDescriptor(
                ForeignKeyOrderModule::class,
                [],
                [],
                tables: ['orders', 'order_items'],
            ),
            new ModuleDescriptor(ForeignKeyUserModule::class, [], [], tables: ['users']),
        ], new SourceIndex([], [], [], [], $references));
    }

    /** @param class-string<Module> $moduleClass */
    private function module(string $name, string $moduleClass): DiscoveredModule
    {
        return new DiscoveredModule(
            $name,
            $moduleClass,
            "/modules/{$name}/{$name}Module.php",
            __NAMESPACE__,
        );
    }
}

final class ForeignKeyUserModule extends Module
{
}

final class ForeignKeyOrderModule extends Module
{
}

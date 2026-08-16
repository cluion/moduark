<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\Rules\CrossModuleTransactionsRule;
use Cluion\Moduark\Analysis\Source\SourceIndex;
use Cluion\Moduark\Analysis\Source\TransactionScope;
use Cluion\Moduark\Analysis\Source\TransactionWrite;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use PHPUnit\Framework\TestCase;

final class CrossModuleTransactionsRuleTest extends TestCase
{
    public function test_cross_owner_unresolved_and_unowned_writes_are_classified(): void
    {
        $result = (new CrossModuleTransactionsRule)->inspect(
            $this->context(),
            RuleId::CrossModuleTransactions->defaultSeverity(),
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
            'rule' => 'cross_module_transactions',
            'code' => 'MOD-TRANSACTION-001',
            'severity' => 'warning',
            'message' => 'Transaction [DB::transaction] directly mutates tables owned by multiple Modules [Order, User].',
            'file' => '/modules/Workflow/Actions/PlaceOrder.php',
            'line' => 20,
            'consumer' => 'Workflow',
            'target' => 'Order, User',
            'symbol' => 'orders, users',
            'suggestion' => 'Move cross-owner orchestration behind Ports, or keep the atomic workflow with a narrow reviewed suppression.',
        ], $violations['MOD-TRANSACTION-001']->toArray());
        self::assertSame('DB::update(sql:*)', $violations['MOD-TRANSACTION-002']->symbol());
        self::assertSame('audit_logs', $violations['MOD-TRANSACTION-003']->symbol());
    }

    public function test_unresolved_evidence_remains_a_warning_when_the_rule_is_elevated(): void
    {
        $result = (new CrossModuleTransactionsRule)->inspect(
            $this->context(),
            Severity::Error,
        );
        $severities = [];

        foreach ($result->violations() as $violation) {
            $severities[$violation->code()] = $violation->severity();
        }

        self::assertSame(Severity::Error, $severities['MOD-TRANSACTION-001']);
        self::assertSame(Severity::Warning, $severities['MOD-TRANSACTION-002']);
        self::assertSame(Severity::Error, $severities['MOD-TRANSACTION-003']);
    }

    private function context(): AnalysisContext
    {
        $registry = new ModuleRegistry([
            $this->module('Order', TransactionOrderModule::class),
            $this->module('User', TransactionUserModule::class),
            $this->module('Workflow', TransactionWorkflowModule::class),
        ]);
        $file = '/modules/Workflow/Actions/PlaceOrder.php';
        $scopes = [
            $this->scope($file, 10, [
                new TransactionWrite('orders', null, 'QueryBuilder::update', 11),
                new TransactionWrite('order_items', null, 'QueryBuilder::insert', 12),
            ]),
            $this->scope($file, 20, [
                new TransactionWrite('users', null, 'QueryBuilder::update', 21),
                new TransactionWrite('orders', null, 'QueryBuilder::insert', 22),
                new TransactionWrite('USERS', null, 'QueryBuilder::increment', 23),
            ]),
            $this->scope($file, 30, [
                new TransactionWrite(null, 'DB::update(sql:*)', 'DB::update', 31),
            ]),
            $this->scope($file, 40, [
                new TransactionWrite('audit_logs', null, 'QueryBuilder::insert', 41),
            ]),
        ];

        return new AnalysisContext($registry, [
            new ModuleDescriptor(
                TransactionOrderModule::class,
                [],
                [],
                tables: ['orders', 'order_items'],
            ),
            new ModuleDescriptor(TransactionUserModule::class, [], [], tables: ['users']),
            new ModuleDescriptor(TransactionWorkflowModule::class, [], []),
        ], new SourceIndex([], [], [], [], [], $scopes));
    }

    /** @param list<TransactionWrite> $writes */
    private function scope(string $file, int $line, array $writes): TransactionScope
    {
        return new TransactionScope(
            TransactionWorkflowModule::class,
            'DB::transaction',
            $writes,
            $file,
            $line,
        );
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

final class TransactionOrderModule extends Module
{
}

final class TransactionUserModule extends Module
{
}

final class TransactionWorkflowModule extends Module
{
}

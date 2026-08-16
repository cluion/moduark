<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\Rules\DatabaseOwnershipRule;
use Cluion\Moduark\Analysis\Source\SourceIndex;
use Cluion\Moduark\Analysis\Source\TableAccess;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use PHPUnit\Framework\TestCase;

final class DatabaseOwnershipRuleTest extends TestCase
{
    public function test_cross_module_unowned_and_unresolved_table_accesses_are_classified(): void
    {
        $result = (new DatabaseOwnershipRule)->inspect(
            $this->context(),
            RuleId::DatabaseOwnership->defaultSeverity(),
        );

        self::assertFalse($result->passed());
        self::assertTrue($result->hasErrors());
        self::assertTrue($result->hasWarnings());
        self::assertCount(3, $result->violations());
        self::assertSame([
            'rule' => 'database_ownership',
            'code' => 'MOD-TABLE-001',
            'severity' => 'error',
            'message' => 'Module [Order] directly accesses table [users] owned by Module [User].',
            'file' => '/modules/Order/Actions/QueryUsers.php',
            'line' => 20,
            'consumer' => 'Order',
            'target' => 'User',
            'symbol' => 'users',
            'suggestion' => "Query [users] through Module [User]'s Port or exported boundary instead.",
        ], $result->violations()[0]->toArray());
        self::assertSame('MOD-TABLE-002', $result->violations()[1]->code());
        self::assertSame('audit_logs', $result->violations()[1]->symbol());
        self::assertSame('MOD-TABLE-003', $result->violations()[2]->code());
        self::assertSame(Severity::Warning, $result->violations()[2]->severity());
        self::assertSame('DB::table(*)', $result->violations()[2]->symbol());
    }

    private function context(): AnalysisContext
    {
        $registry = new ModuleRegistry([
            $this->module('User', DatabaseUserModule::class),
            $this->module('Order', DatabaseOrderModule::class),
        ]);
        $file = '/modules/Order/Actions/QueryUsers.php';
        $accesses = [
            new TableAccess(DatabaseOrderModule::class, 'orders', null, 'DB::table', $file, 10),
            new TableAccess(DatabaseOrderModule::class, 'users', null, 'leftjoin', $file, 20),
            new TableAccess(DatabaseOrderModule::class, 'audit_logs', null, 'DB::table', $file, 30),
            new TableAccess(DatabaseOrderModule::class, null, null, 'DB::table', $file, 40),
            new TableAccess(DatabaseUserModule::class, 'USERS', null, 'Schema::table', $file, 50),
        ];

        return new AnalysisContext($registry, [
            new ModuleDescriptor(DatabaseOrderModule::class, [], [], tables: ['orders']),
            new ModuleDescriptor(DatabaseUserModule::class, [], [], tables: ['users']),
        ], new SourceIndex([], [], $accesses));
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

final class DatabaseUserModule extends Module
{
}

final class DatabaseOrderModule extends Module
{
}

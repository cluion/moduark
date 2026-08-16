<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\Rules\MigrationOwnershipRule;
use Cluion\Moduark\Analysis\Source\SchemaMutation;
use Cluion\Moduark\Analysis\Source\SourceIndex;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use PHPUnit\Framework\TestCase;

final class MigrationOwnershipRuleTest extends TestCase
{
    public function test_migration_table_ownership_location_and_unresolved_evidence_are_classified(): void
    {
        $result = (new MigrationOwnershipRule)->inspect(
            $this->context(),
            RuleId::MigrationOwnership->defaultSeverity(),
        );

        self::assertFalse($result->passed());
        self::assertTrue($result->hasErrors());
        self::assertTrue($result->hasWarnings());
        self::assertCount(4, $result->violations());

        $violations = [];

        foreach ($result->violations() as $violation) {
            $violations[$violation->code()] = $violation;
        }

        self::assertSame([
            'rule' => 'migration_ownership',
            'code' => 'MOD-MIGRATION-001',
            'severity' => 'error',
            'message' => 'Module [Order] migration [Schema::table(table)] changes table [users] owned by Module [User].',
            'file' => '/modules/Order/Database/Migrations/2026_08_16_000000_orders.php',
            'line' => 20,
            'consumer' => 'Order',
            'target' => 'User',
            'symbol' => 'users',
            'suggestion' => 'Move this schema change to Module [User] or record a reviewed orchestration suppression.',
        ], $violations['MOD-MIGRATION-001']->toArray());
        self::assertSame('audit_logs', $violations['MOD-MIGRATION-002']->symbol());
        self::assertSame('Schema::create', $violations['MOD-MIGRATION-003']->symbol());
        self::assertSame(Severity::Warning, $violations['MOD-MIGRATION-004']->severity());
        self::assertSame('Schema::drop(table:*)', $violations['MOD-MIGRATION-004']->symbol());
    }

    private function context(): AnalysisContext
    {
        $registry = new ModuleRegistry([
            $this->module('User', MigrationUserModule::class),
            $this->module('Order', MigrationOrderModule::class),
        ]);
        $migration = '/modules/Order/Database/Migrations/2026_08_16_000000_orders.php';
        $mutations = [
            new SchemaMutation(MigrationOrderModule::class, 'ORDERS', null, 'Schema::create', 'table', $migration, 10),
            new SchemaMutation(MigrationOrderModule::class, 'users', null, 'Schema::table', 'table', $migration, 20),
            new SchemaMutation(MigrationOrderModule::class, 'audit_logs', null, 'Schema::dropIfExists', 'table', $migration, 30),
            new SchemaMutation(MigrationOrderModule::class, null, null, 'Schema::drop', 'table', $migration, 40),
            new SchemaMutation(MigrationOrderModule::class, 'legacy_orders', null, 'Schema::rename', 'from', $migration, 50),
            new SchemaMutation(MigrationOrderModule::class, 'orders', null, 'Schema::rename', 'to', $migration, 50),
            new SchemaMutation(
                MigrationOrderModule::class,
                'orders',
                null,
                'Schema::create',
                'table',
                '/modules/Order/Actions/BootstrapSchema.php',
                60,
            ),
        ];

        return new AnalysisContext($registry, [
            new ModuleDescriptor(
                MigrationOrderModule::class,
                [],
                [],
                tables: ['orders', 'legacy_orders'],
            ),
            new ModuleDescriptor(MigrationUserModule::class, [], [], tables: ['users']),
        ], new SourceIndex([], [], [], $mutations));
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

final class MigrationUserModule extends Module
{
}

final class MigrationOrderModule extends Module
{
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\Source\SourceIndex;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Exceptions\InvalidModuleMetadata;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Module;
use Cluion\Moduark\Persistence\TableOwnershipIndex;
use Cluion\Moduark\Registry\ModuleRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TableOwnershipIndexTest extends TestCase
{
    public function test_it_indexes_canonical_table_names_with_one_case_insensitive_owner(): void
    {
        $descriptors = (new ModuleMetadataCompiler)->compileAll([
            TableUserModule::class,
            TableOrderModule::class,
        ]);
        $index = new TableOwnershipIndex($descriptors);

        self::assertSame(TableUserModule::class, $index->owner('users'));
        self::assertSame(TableUserModule::class, $index->owner('USERS'));
        self::assertSame(TableOrderModule::class, $index->owner('audit.events'));
        self::assertNull($index->owner('missing'));
        self::assertTrue($index->owns(TableOrderModule::class, 'ORDERS'));
        self::assertFalse($index->owns(TableUserModule::class, 'orders'));
        self::assertSame([
            'audit.events',
            'order_items',
            'orders',
        ], $index->tablesFor(TableOrderModule::class));
        self::assertSame([
            'audit.events' => TableOrderModule::class,
            'order_items' => TableOrderModule::class,
            'orders' => TableOrderModule::class,
            'user_profiles' => TableUserModule::class,
            'Users' => TableUserModule::class,
        ], $index->all());
    }

    public function test_analysis_context_exposes_the_validated_table_ownership_index(): void
    {
        $registry = new ModuleRegistry([
            $this->module('Order', TableOrderModule::class),
            $this->module('User', TableUserModule::class),
        ]);
        $descriptors = (new ModuleMetadataCompiler)->compileAll($registry->moduleClasses());
        $context = new AnalysisContext($registry, $descriptors, new SourceIndex([], []));

        self::assertSame(TableOrderModule::class, $context->tableOwnership()->owner('orders'));
        self::assertSame(TableUserModule::class, $context->tableOwnership()->owner('users'));
    }

    #[DataProvider('invalidTableMetadata')]
    public function test_compiler_rejects_non_canonical_table_metadata(
        string $moduleClass,
        string $received,
    ): void {
        $this->expectException(InvalidModuleMetadata::class);
        $this->expectExceptionMessage(
            "{$moduleClass}::tables() must return unquoted dot-separated table names; received {$received}.",
        );

        (new ModuleMetadataCompiler)->compile($moduleClass);
    }

    /** @return iterable<string, array{class-string<Module>, string}> */
    public static function invalidTableMetadata(): iterable
    {
        yield 'empty string' => [EmptyTableNameModule::class, '[]'];
        yield 'query alias' => [AliasedTableNameModule::class, '[users as u]'];
        yield 'quoted identifier' => [QuotedTableNameModule::class, '[`users`]'];
        yield 'numeric identifier' => [NumericTableNameModule::class, '[123]'];
        yield 'empty qualified segment' => [InvalidQualifiedTableModule::class, '[audit..events]'];
    }

    public function test_duplicate_table_names_inside_one_module_are_case_insensitive(): void
    {
        $this->expectException(InvalidModuleMetadata::class);
        $this->expectExceptionMessage(
            DuplicateTableModule::class.'::tables() contains duplicate reference [users].',
        );

        (new ModuleMetadataCompiler)->compile(DuplicateTableModule::class);
    }

    public function test_one_table_cannot_be_owned_by_multiple_modules(): void
    {
        $descriptors = (new ModuleMetadataCompiler)->compileAll([
            DuplicateOwnerUserModule::class,
            DuplicateOwnerOrderModule::class,
        ]);

        $this->expectException(InvalidModuleMetadata::class);
        $this->expectExceptionMessage(
            'Table [users] is owned by multiple Modules: ['
            .DuplicateOwnerOrderModule::class.', '.DuplicateOwnerUserModule::class.'].',
        );

        new TableOwnershipIndex($descriptors);
    }

    public function test_duplicate_descriptors_are_rejected(): void
    {
        $descriptor = (new ModuleMetadataCompiler)->compile(TableOrderModule::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Table ownership descriptor ['.TableOrderModule::class.'] was provided more than once.',
        );

        new TableOwnershipIndex([$descriptor, $descriptor]);
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

final class TableUserModule extends Module
{
    public function tables(): array
    {
        return ['Users', 'user_profiles'];
    }
}

final class TableOrderModule extends Module
{
    public function tables(): array
    {
        return ['orders', 'order_items', 'audit.events'];
    }
}

final class EmptyTableNameModule extends Module
{
    public function tables(): array
    {
        return [''];
    }
}

final class AliasedTableNameModule extends Module
{
    public function tables(): array
    {
        return ['users as u'];
    }
}

final class QuotedTableNameModule extends Module
{
    public function tables(): array
    {
        return ['`users`'];
    }
}

final class InvalidQualifiedTableModule extends Module
{
    public function tables(): array
    {
        return ['audit..events'];
    }
}

final class NumericTableNameModule extends Module
{
    public function tables(): array
    {
        return ['123'];
    }
}

final class DuplicateTableModule extends Module
{
    public function tables(): array
    {
        return ['Users', 'users'];
    }
}

final class DuplicateOwnerUserModule extends Module
{
    public function tables(): array
    {
        return ['Users'];
    }
}

final class DuplicateOwnerOrderModule extends Module
{
    public function tables(): array
    {
        return ['users'];
    }
}

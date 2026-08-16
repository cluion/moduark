<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Analysis\Source\SourceIndexBuilder;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Exceptions\SourceAnalysisFailed;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Analysis\Modules\Order\OrderModule;
use Tests\Fixtures\Analysis\Modules\User\UserModule;

final class SourceIndexBuilderTest extends TestCase
{
    private ?string $temporaryPath = null;

    protected function tearDown(): void
    {
        if ($this->temporaryPath !== null) {
            (new Filesystem)->deleteDirectory($this->temporaryPath);
        }

        parent::tearDown();
    }

    public function test_it_builds_deterministic_symbol_ownership_and_class_references(): void
    {
        $first = (new SourceIndexBuilder($this->registry()))->build();
        $second = (new SourceIndexBuilder($this->registry()))->build();
        $service = $first->symbol(
            'tests\\fixtures\\analysis\\modules\\user\\services\\userservice',
        );

        self::assertNotNull($service);
        self::assertSame(UserModule::class, $service->owner());
        self::assertSame(
            array_map(static fn ($symbol): array => $symbol->toArray(), $first->symbols()),
            array_map(static fn ($symbol): array => $symbol->toArray(), $second->symbols()),
        );
        self::assertSame(
            array_map(static fn ($reference): array => $reference->toArray(), $first->references()),
            array_map(static fn ($reference): array => $reference->toArray(), $second->references()),
        );

        $crossModuleSymbols = array_values(array_unique(array_map(
            static fn ($reference): string => $reference->symbol(),
            array_filter(
                $first->referencesFrom(OrderModule::class),
                static fn ($reference): bool => $reference->target() === UserModule::class,
            ),
        )));
        sort($crossModuleSymbols, SORT_STRING);

        self::assertSame([
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Attributes\\UserMarker',
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Base\\UserBase',
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Contracts\\SecondContract',
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Contracts\\UserContract',
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Data\\UserData',
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Events\\UserCreated',
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Exceptions\\UserFailure',
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Services\\UserService',
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Support\\UserTrait',
        ], $crossModuleSymbols);
        self::assertNotContains(
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Internal\\UnusedService',
            $crossModuleSymbols,
        );
        self::assertNotEmpty(array_filter(
            $first->referencesFrom(OrderModule::class),
            static fn ($reference): bool => $reference->target() === OrderModule::class,
        ));
    }

    public function test_invalid_php_is_an_analysis_failure_with_source_location(): void
    {
        $this->temporaryPath = sys_get_temp_dir().'/moduark-source-'.bin2hex(random_bytes(6));
        $modulePath = $this->temporaryPath.'/Broken';
        self::assertTrue(mkdir($modulePath, 0755, true));
        self::assertNotFalse(file_put_contents(
            $modulePath.'/BrokenModule.php',
            "<?php\nnamespace Broken;\nfinal class BrokenModule {",
        ));
        $registry = new ModuleRegistry([
            new DiscoveredModule(
                'Broken',
                BrokenSourceModule::class,
                $modulePath.'/BrokenModule.php',
                'Broken',
            ),
        ]);

        try {
            (new SourceIndexBuilder($registry))->build();
            self::fail('Invalid PHP must fail source analysis.');
        } catch (SourceAnalysisFailed $exception) {
            self::assertStringContainsString(
                "Unable to parse Module source [{$modulePath}/BrokenModule.php:3]",
                $exception->getMessage(),
            );
            self::assertSame('MOD-ANALYSIS-001', $exception->diagnosticCode());
            self::assertSame("{$modulePath}/BrokenModule.php:3", $exception->location());
            self::assertSame(
                'Fix the PHP syntax at the reported location, then rerun module:check.',
                $exception->suggestion(),
            );
        }
    }

    public function test_duplicate_case_insensitive_symbols_are_rejected(): void
    {
        $this->temporaryPath = sys_get_temp_dir().'/moduark-source-'.bin2hex(random_bytes(6));
        $alphaPath = $this->temporaryPath.'/Alpha/AlphaModule.php';
        $betaPath = $this->temporaryPath.'/Beta/BetaModule.php';
        self::assertTrue(mkdir(dirname($alphaPath), 0755, true));
        self::assertTrue(mkdir(dirname($betaPath), 0755, true));
        self::assertNotFalse(file_put_contents(
            $alphaPath,
            "<?php\nnamespace Duplicate;\nfinal class SharedSymbol {}",
        ));
        self::assertNotFalse(file_put_contents(
            $betaPath,
            "<?php\nnamespace Duplicate;\nfinal class SHAREDSYMBOL {}",
        ));
        $registry = new ModuleRegistry([
            new DiscoveredModule('Beta', DuplicateSourceModule::class, $betaPath, 'Duplicate'),
            new DiscoveredModule('Alpha', BrokenSourceModule::class, $alphaPath, 'Duplicate'),
        ]);

        $this->expectException(SourceAnalysisFailed::class);
        $this->expectExceptionMessage(
            "Module symbol [Duplicate\\SHAREDSYMBOL] is declared by both [{$alphaPath}] and [{$betaPath}].",
        );

        (new SourceIndexBuilder($registry))->build();
    }

    public function test_it_collects_laravel_facade_and_fluent_query_table_accesses(): void
    {
        $this->temporaryPath = sys_get_temp_dir().'/moduark-query-'.bin2hex(random_bytes(6));
        $modulePath = $this->temporaryPath.'/Query/QueryModule.php';
        self::assertTrue(mkdir(dirname($modulePath), 0755, true));
        self::assertNotFalse(file_put_contents($modulePath, <<<'PHP'
<?php

namespace QueryFixture;

use Illuminate\Support\Facades\DB as Database;
use Illuminate\Support\Facades\Schema;

final class QueryModuleEntry
{
    public function run(string $table, object $custom): void
    {
        Database::table('orders as o')->leftJoin('users AS u', 'u.id', '=', 'o.user_id');
        Database::query()->from('audit.events', 'events');
        Schema::table('profiles', static function (): void {});
        Database::table($table);
        Database::table('(select 1) as derived');
        Database::connection('tenant')->table('tenant_users');
        Schema::connection('tenant')->table('tenant_profiles', static function (): void {});
        $custom->join('ignored', 'ignored.id', '=', 'ignored.id');
        Database::table('orders')->joinSub(Database::table('users'), 'users', 'users.id', '=', 'orders.user_id');
    }
}
PHP));
        $registry = new ModuleRegistry([
            new DiscoveredModule(
                'Query',
                QuerySourceModule::class,
                $modulePath,
                'QueryFixture',
            ),
        ]);

        $first = (new SourceIndexBuilder($registry))->build();
        $second = (new SourceIndexBuilder($registry))->build();
        $evidence = array_map(
            static fn ($access): array => [$access->operation(), $access->evidence()],
            $first->tableAccessesFrom(QuerySourceModule::class),
        );
        sort($evidence);

        self::assertSame([
            ['DB::connection()->table', 'tenant_users'],
            ['DB::table', '(select 1) as derived'],
            ['DB::table', 'DB::table(*)'],
            ['DB::table', 'orders'],
            ['DB::table', 'orders'],
            ['DB::table', 'users'],
            ['Schema::connection()->table', 'tenant_profiles'],
            ['Schema::table', 'profiles'],
            ['from', 'audit.events'],
            ['leftjoin', 'users'],
        ], $evidence);
        self::assertSame(
            array_map(static fn ($access): array => $access->toArray(), $first->tableAccesses()),
            array_map(static fn ($access): array => $access->toArray(), $second->tableAccesses()),
        );
        self::assertNotContains(
            'ignored',
            array_map(static fn ($access): string => $access->evidence(), $first->tableAccesses()),
        );
    }

    public function test_it_collects_laravel_schema_mutations_without_guessing_dynamic_tables(): void
    {
        $this->temporaryPath = sys_get_temp_dir().'/moduark-migration-'.bin2hex(random_bytes(6));
        $modulePath = $this->temporaryPath.'/Migration/MigrationModule.php';
        $migrationPath = $this->temporaryPath
            .'/Migration/Database/Migrations/2026_08_16_000000_orders.php';
        self::assertTrue(mkdir(dirname($migrationPath), 0755, true));
        self::assertNotFalse(file_put_contents($modulePath, "<?php\nnamespace MigrationFixture;\nfinal class MigrationModuleEntry {}\n"));
        self::assertNotFalse(file_put_contents($migrationPath, <<<'PHP'
<?php

namespace MigrationFixture;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema as DatabaseSchema;

return new class extends Migration
{
    public function up(string $table, object $custom): void
    {
        DatabaseSchema::create('orders', static function (): void {});
        DatabaseSchema::table($table, static function (): void {});
        DatabaseSchema::rename('legacy_orders', 'orders');
        DatabaseSchema::drop('order_archive');
        DatabaseSchema::dropIfExists('order_drafts');
        DatabaseSchema::connection('tenant')->table('tenant.orders', static function (): void {});
        DatabaseSchema::create('orders as o', static function (): void {});
        \Illuminate\Support\Facades\Schema::dropIfExists('fully_qualified');
        $custom->drop('ignored');
    }
};
PHP));
        $registry = new ModuleRegistry([
            new DiscoveredModule(
                'Migration',
                MigrationSourceModule::class,
                $modulePath,
                'MigrationFixture',
            ),
        ]);

        $first = (new SourceIndexBuilder($registry))->build();
        $second = (new SourceIndexBuilder($registry))->build();
        $evidence = array_map(
            static fn ($mutation): array => [
                $mutation->operation(),
                $mutation->operand(),
                $mutation->evidence(),
            ],
            $first->schemaMutationsFrom(MigrationSourceModule::class),
        );

        self::assertSame([
            ['Schema::create', 'table', 'orders'],
            ['Schema::table', 'table', 'Schema::table(table:*)'],
            ['Schema::rename', 'from', 'legacy_orders'],
            ['Schema::rename', 'to', 'orders'],
            ['Schema::drop', 'table', 'order_archive'],
            ['Schema::dropIfExists', 'table', 'order_drafts'],
            ['Schema::connection()->table', 'table', 'tenant.orders'],
            ['Schema::create', 'table', 'orders as o'],
            ['Schema::dropIfExists', 'table', 'fully_qualified'],
        ], $evidence);
        self::assertSame(
            array_map(static fn ($mutation): array => $mutation->toArray(), $first->schemaMutations()),
            array_map(static fn ($mutation): array => $mutation->toArray(), $second->schemaMutations()),
        );
        self::assertNotContains(
            'ignored',
            array_map(
                static fn ($mutation): string => $mutation->evidence(),
                $first->schemaMutations(),
            ),
        );
    }

    private function registry(): ModuleRegistry
    {
        $root = dirname(__DIR__).'/Fixtures/Analysis/Modules';

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
            "Tests\\Fixtures\\Analysis\\Modules\\{$name}",
        );
    }
}

final class BrokenSourceModule extends Module
{
}

final class DuplicateSourceModule extends Module
{
}

final class QuerySourceModule extends Module
{
}

final class MigrationSourceModule extends Module
{
}

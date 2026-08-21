<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Analysis\Source\SourceAnalysisCache;
use Cluion\Moduark\Analysis\Source\SourceAnalysisCacheStore;
use Cluion\Moduark\Analysis\Source\SourceIndex;
use Cluion\Moduark\Analysis\Source\SourceIndexBuilder;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Exceptions\SourceAnalysisFailed;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

final class SourceAnalysisCacheTest extends TestCase
{
    private string $temporaryPath;

    private string $modulePath;

    private string $trackedPath;

    private SourceAnalysisCacheStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryPath = sys_get_temp_dir().'/moduark-analysis-cache-'.bin2hex(random_bytes(6));
        $this->modulePath = $this->temporaryPath.'/Incremental/IncrementalModule.php';
        $this->trackedPath = $this->temporaryPath.'/Incremental/Internal/Tracked.php';
        $this->store = new SourceAnalysisCacheStore(
            $this->temporaryPath.'/bootstrap/cache/moduark-analysis.php',
        );

        self::assertTrue(mkdir(dirname($this->trackedPath), 0755, true));
        $this->write($this->modulePath, <<<'PHP'
<?php

namespace Incremental;

final class IncrementalModuleEntry
{
}
PHP);
        $this->writeTracked('Tracked');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->temporaryPath);

        parent::tearDown();
    }

    public function test_it_reuses_unchanged_files_and_invalidates_changed_or_removed_files(): void
    {
        $builder = $this->builder(IncrementalSourceModule::class);
        $first = $builder->build();
        $firstCache = (string) file_get_contents($this->store->path());

        self::assertNotNull($first->symbol('Incremental\\Internal\\Tracked'));
        self::assertFileExists($this->store->path());
        self::assertCount(2, $this->cachePayload()['files']);

        $second = $builder->build();

        self::assertSame($this->indexPayload($first), $this->indexPayload($second));
        self::assertSame($firstCache, file_get_contents($this->store->path()));

        $this->writeTracked('Renamed');
        $changed = $builder->build();

        self::assertNull($changed->symbol('Incremental\\Internal\\Tracked'));
        self::assertNotNull($changed->symbol('Incremental\\Internal\\Renamed'));
        self::assertNotSame($firstCache, file_get_contents($this->store->path()));

        self::assertTrue(unlink($this->trackedPath));
        $removed = $builder->build();

        self::assertNull($removed->symbol('Incremental\\Internal\\Renamed'));
        self::assertCount(1, $this->cachePayload()['files']);
    }

    public function test_it_invalidates_entries_when_module_ownership_changes(): void
    {
        $first = $this->builder(IncrementalSourceModule::class)->build();
        $second = $this->builder(AlternateSourceModule::class)->build();

        self::assertSame(
            IncrementalSourceModule::class,
            $first->symbol('Incremental\\Internal\\Tracked')?->owner(),
        );
        self::assertSame(
            AlternateSourceModule::class,
            $second->symbol('Incremental\\Internal\\Tracked')?->owner(),
        );
    }

    public function test_unchanged_references_are_resolved_against_current_global_ownership(): void
    {
        $consumerPath = $this->temporaryPath.'/Consumer/ConsumerModule.php';
        self::assertTrue(mkdir(dirname($consumerPath), 0755, true));
        $this->write($consumerPath, <<<'PHP'
<?php

namespace Consumer;

use Incremental\Internal\Tracked;

final class ConsumerModuleEntry
{
    public function accept(Tracked $tracked): Tracked
    {
        return $tracked;
    }
}
PHP);
        $first = $this->builderWithConsumer(IncrementalSourceModule::class, $consumerPath)->build();
        $second = $this->builderWithConsumer(AlternateSourceModule::class, $consumerPath)->build();

        self::assertSame(
            [IncrementalSourceModule::class],
            array_values(array_unique(array_map(
                static fn ($reference): string => $reference->target(),
                $first->referencesFrom(ConsumerSourceModule::class),
            ))),
        );
        self::assertSame(
            [AlternateSourceModule::class],
            array_values(array_unique(array_map(
                static fn ($reference): string => $reference->target(),
                $second->referencesFrom(ConsumerSourceModule::class),
            ))),
        );
    }

    public function test_eloquent_model_ancestry_survives_a_warm_source_analysis_cache(): void
    {
        $this->write($this->trackedPath, <<<'PHP'
<?php

namespace Incremental\Internal;

use Illuminate\Database\Eloquent\Model as EloquentModel;

final class Tracked extends EloquentModel
{
}
PHP);
        $builder = $this->builder(IncrementalSourceModule::class);

        $cold = $builder->build();
        $warm = $builder->build();

        self::assertTrue($cold->isEloquentModel('Incremental\Internal\Tracked'));
        self::assertTrue($warm->isEloquentModel('Incremental\Internal\Tracked'));
        self::assertSame(7, $this->cachePayload()['schema_version']);
    }

    public function test_table_access_evidence_survives_a_warm_source_analysis_cache(): void
    {
        $this->write($this->trackedPath, <<<'PHP'
<?php

namespace Incremental\Internal;

use Illuminate\Support\Facades\DB;

final class Tracked
{
    public function query(): mixed
    {
        return DB::table(
            'orders as o',
        )->leftJoin(
            'users',
            'users.id',
            '=',
            'o.user_id',
        );
    }
}
PHP);
        $builder = $this->builder(IncrementalSourceModule::class);

        $cold = $builder->build();
        $warm = $builder->build();

        self::assertSame([
            ['DB::table', 'orders'],
            ['leftjoin', 'users'],
        ], array_map(
            static fn ($access): array => [$access->operation(), $access->evidence()],
            $cold->tableAccesses(),
        ));
        self::assertSame($this->indexPayload($cold), $this->indexPayload($warm));
        self::assertSame(7, $this->cachePayload()['schema_version']);
        self::assertSame([12, 14], array_map(
            static fn ($access): int => $access->line(),
            $warm->tableAccesses(),
        ));
    }

    public function test_schema_mutation_evidence_survives_a_warm_source_analysis_cache(): void
    {
        $this->write($this->trackedPath, <<<'PHP'
<?php

namespace Incremental\Internal;

use Illuminate\Support\Facades\Schema;

final class Tracked
{
    public function migrate(string $table): void
    {
        Schema::rename('legacy_orders', 'orders');
        Schema::connection('tenant')->dropIfExists($table);
    }
}
PHP);
        $builder = $this->builder(IncrementalSourceModule::class);

        $cold = $builder->build();
        $warm = $builder->build();

        self::assertSame([
            ['Schema::rename', 'from', 'legacy_orders'],
            ['Schema::rename', 'to', 'orders'],
            ['Schema::connection()->dropIfExists', 'table', 'Schema::connection()->dropIfExists(table:*)'],
        ], array_map(
            static fn ($mutation): array => [
                $mutation->operation(),
                $mutation->operand(),
                $mutation->evidence(),
            ],
            $cold->schemaMutations(),
        ));
        self::assertSame($this->indexPayload($cold), $this->indexPayload($warm));
        self::assertSame(7, $this->cachePayload()['schema_version']);
    }

    public function test_foreign_key_evidence_survives_a_warm_source_analysis_cache(): void
    {
        $this->write($this->trackedPath, <<<'PHP'
<?php

namespace Incremental\Internal;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::table('orders', function (Blueprint $table): void {
    $table->foreignId('user_id')->constrained('users');
});
PHP);
        $builder = $this->builder(IncrementalSourceModule::class);

        $cold = $builder->build();
        $warm = $builder->build();

        self::assertSame([
            ['Blueprint::foreignId()->constrained', 'orders -> users'],
        ], array_map(
            static fn ($reference): array => [
                $reference->operation(),
                $reference->evidence(),
            ],
            $cold->foreignKeyReferences(),
        ));
        self::assertSame($this->indexPayload($cold), $this->indexPayload($warm));
        self::assertSame(7, $this->cachePayload()['schema_version']);
    }

    public function test_transaction_scope_evidence_survives_a_warm_source_analysis_cache(): void
    {
        $this->write($this->trackedPath, <<<'PHP'
<?php

namespace Incremental\Internal;

use Illuminate\Support\Facades\DB;

DB::transaction(function (): void {
    DB::table('orders')->update(['status' => 'paid']);
    DB::query()->from('users')->insert(['name' => 'Ada']);
});
PHP);
        $builder = $this->builder(IncrementalSourceModule::class);

        $cold = $builder->build();
        $warm = $builder->build();

        self::assertSame([
            [
                'DB::transaction',
                [
                    ['QueryBuilder::update', 'orders'],
                    ['QueryBuilder::insert', 'users'],
                ],
            ],
        ], array_map(
            static fn ($scope): array => [
                $scope->operation(),
                array_map(
                    static fn ($write): array => [$write->operation(), $write->evidence()],
                    $scope->writes(),
                ),
            ],
            $cold->transactionScopes(),
        ));
        self::assertSame($this->indexPayload($cold), $this->indexPayload($warm));
        self::assertSame(7, $this->cachePayload()['schema_version']);
    }

    public function test_an_invalid_cache_falls_back_to_a_complete_cold_analysis(): void
    {
        $builder = $this->builder(IncrementalSourceModule::class);
        $expected = $this->indexPayload($builder->build());

        self::assertIsInt(file_put_contents(
            $this->store->path(),
            "<?php\n\nreturn ['schema_version' => 999, 'files' => 'invalid'];\n",
        ));

        $actual = $builder->build();

        self::assertSame($expected, $this->indexPayload($actual));
        self::assertSame(SourceAnalysisCache::SCHEMA_VERSION, $this->cachePayload()['schema_version']);
    }

    public function test_malformed_current_schema_table_evidence_falls_back_to_cold_analysis(): void
    {
        $builder = $this->builder(IncrementalSourceModule::class);
        $expected = $this->indexPayload($builder->build());
        $payload = $this->cachePayload();
        $tracked = $payload['files'][$this->trackedPath] ?? null;
        self::assertIsArray($tracked);
        $tracked['table_accesses'] = [[
            'table' => 'users as u',
            'expression' => null,
            'operation' => 'DB::table',
            'line' => 1,
        ]];
        $payload['files'][$this->trackedPath] = $tracked;
        self::assertIsInt(file_put_contents(
            $this->store->path(),
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ".var_export($payload, true).";\n",
        ));

        $actual = $builder->build();

        self::assertSame($expected, $this->indexPayload($actual));
        $repaired = $this->cachePayload()['files'][$this->trackedPath] ?? null;
        self::assertIsArray($repaired);
        self::assertSame([], $repaired['table_accesses'] ?? []);
    }

    public function test_malformed_current_schema_mutation_evidence_falls_back_to_cold_analysis(): void
    {
        $builder = $this->builder(IncrementalSourceModule::class);
        $expected = $this->indexPayload($builder->build());
        $payload = $this->cachePayload();
        $tracked = $payload['files'][$this->trackedPath] ?? null;
        self::assertIsArray($tracked);
        $tracked['schema_mutations'] = [[
            'table' => 'orders as o',
            'expression' => null,
            'operation' => 'Schema::create',
            'operand' => 'table',
            'line' => 1,
        ]];
        $payload['files'][$this->trackedPath] = $tracked;
        self::assertIsInt(file_put_contents(
            $this->store->path(),
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ".var_export($payload, true).";\n",
        ));

        $actual = $builder->build();

        self::assertSame($expected, $this->indexPayload($actual));
        $repaired = $this->cachePayload()['files'][$this->trackedPath] ?? null;
        self::assertIsArray($repaired);
        self::assertSame([], $repaired['schema_mutations'] ?? []);
    }

    public function test_malformed_current_schema_foreign_key_evidence_falls_back_to_cold_analysis(): void
    {
        $builder = $this->builder(IncrementalSourceModule::class);
        $expected = $this->indexPayload($builder->build());
        $payload = $this->cachePayload();
        $tracked = $payload['files'][$this->trackedPath] ?? null;
        self::assertIsArray($tracked);
        $tracked['foreign_keys'] = [[
            'from_table' => 'orders as o',
            'from_expression' => null,
            'to_table' => 'users',
            'to_expression' => null,
            'operation' => 'Blueprint::foreignId()->constrained',
            'line' => 1,
        ]];
        $payload['files'][$this->trackedPath] = $tracked;
        self::assertIsInt(file_put_contents(
            $this->store->path(),
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ".var_export($payload, true).";\n",
        ));

        $actual = $builder->build();

        self::assertSame($expected, $this->indexPayload($actual));
        $repaired = $this->cachePayload()['files'][$this->trackedPath] ?? null;
        self::assertIsArray($repaired);
        self::assertSame([], $repaired['foreign_keys'] ?? []);
    }

    public function test_malformed_current_schema_transaction_evidence_falls_back_to_cold_analysis(): void
    {
        $builder = $this->builder(IncrementalSourceModule::class);
        $expected = $this->indexPayload($builder->build());
        $payload = $this->cachePayload();
        $tracked = $payload['files'][$this->trackedPath] ?? null;
        self::assertIsArray($tracked);
        $tracked['transaction_scopes'] = [[
            'operation' => 'DB::transaction',
            'writes' => [[
                'table' => 'orders as o',
                'expression' => null,
                'operation' => 'QueryBuilder::update',
                'line' => 2,
            ]],
            'line' => 1,
        ]];
        $payload['files'][$this->trackedPath] = $tracked;
        self::assertIsInt(file_put_contents(
            $this->store->path(),
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ".var_export($payload, true).";\n",
        ));

        $actual = $builder->build();

        self::assertSame($expected, $this->indexPayload($actual));
        $repaired = $this->cachePayload()['files'][$this->trackedPath] ?? null;
        self::assertIsArray($repaired);
        self::assertSame([], $repaired['transaction_scopes'] ?? []);
    }

    public function test_a_changed_file_with_invalid_syntax_never_reuses_or_replaces_the_cache(): void
    {
        $builder = $this->builder(IncrementalSourceModule::class);
        $builder->build();
        $cache = (string) file_get_contents($this->store->path());
        $this->write($this->trackedPath, "<?php\nnamespace Incremental\\Internal;\nfinal class Tracked {");

        try {
            $builder->build();
            self::fail('Changed invalid PHP must fail incremental source analysis.');
        } catch (SourceAnalysisFailed $exception) {
            self::assertStringContainsString($this->trackedPath.':3', $exception->getMessage());
        }

        self::assertSame($cache, file_get_contents($this->store->path()));
    }

    public function test_a_cache_write_failure_does_not_fail_fresh_source_analysis(): void
    {
        $blockingPath = $this->temporaryPath.'/not-a-directory';
        self::assertIsInt(file_put_contents($blockingPath, 'blocked'));
        $store = new SourceAnalysisCacheStore($blockingPath.'/moduark-analysis.php');

        $index = $this->builderUsingStore(IncrementalSourceModule::class, $store)->build();

        self::assertNotNull($index->symbol('Incremental\\Internal\\Tracked'));
        self::assertFileDoesNotExist($store->path());
    }

    /**
     * @param class-string<Module> $owner
     */
    private function builder(string $owner): SourceIndexBuilder
    {
        return $this->builderUsingStore($owner, $this->store);
    }

    /**
     * @param class-string<Module> $owner
     */
    private function builderUsingStore(string $owner, SourceAnalysisCacheStore $store): SourceIndexBuilder
    {
        return new SourceIndexBuilder(
            new ModuleRegistry([
                new DiscoveredModule(
                    'Incremental',
                    $owner,
                    $this->modulePath,
                    'Incremental',
                ),
            ]),
            $store,
        );
    }

    /**
     * @param class-string<Module> $owner
     */
    private function builderWithConsumer(string $owner, string $consumerPath): SourceIndexBuilder
    {
        return new SourceIndexBuilder(
            new ModuleRegistry([
                new DiscoveredModule(
                    'Incremental',
                    $owner,
                    $this->modulePath,
                    'Incremental',
                ),
                new DiscoveredModule(
                    'Consumer',
                    ConsumerSourceModule::class,
                    $consumerPath,
                    'Consumer',
                ),
            ]),
            $this->store,
        );
    }

    private function writeTracked(string $name): void
    {
        $this->write($this->trackedPath, <<<PHP
<?php

namespace Incremental\\Internal;

final class {$name}
{
}
PHP);
    }

    private function write(string $path, string $contents): void
    {
        self::assertIsInt(file_put_contents($path, $contents));
    }

    /**
     * @return array{
     *     symbols: list<array{
     *         name: string,
     *         owner: class-string<Module>,
     *         file: string,
     *         line: int,
     *         parent: ?string
     *     }>,
     *     references: list<array{
     *         source: class-string<Module>,
     *         target: class-string<Module>,
     *         symbol: string,
     *         file: string,
     *         line: int
     *     }>,
     *     table_accesses: list<array{
     *         source: class-string<Module>,
     *         table: ?string,
     *         expression: ?string,
     *         operation: string,
     *         file: string,
     *         line: int
     *     }>,
     *     schema_mutations: list<array{
     *         source: class-string<Module>,
     *         table: ?string,
     *         expression: ?string,
     *         operation: string,
     *         operand: string,
     *         file: string,
     *         line: int
     *     }>,
     *     foreign_keys: list<array{
     *         source: class-string<Module>,
     *         from_table: ?string,
     *         from_expression: ?string,
     *         to_table: ?string,
     *         to_expression: ?string,
     *         operation: string,
     *         file: string,
     *         line: int
     *     }>,
     *     transaction_scopes: list<array{
     *         source: class-string<Module>,
     *         operation: string,
     *         writes: list<array{
     *             table: ?string,
     *             expression: ?string,
     *             operation: string,
     *             line: int
     *         }>,
     *         file: string,
     *         line: int
     *     }>
     * }
     */
    private function indexPayload(SourceIndex $index): array
    {
        return [
            'symbols' => array_map(
                static fn ($symbol): array => $symbol->toArray(),
                $index->symbols(),
            ),
            'references' => array_map(
                static fn ($reference): array => $reference->toArray(),
                $index->references(),
            ),
            'table_accesses' => array_map(
                static fn ($access): array => $access->toArray(),
                $index->tableAccesses(),
            ),
            'schema_mutations' => array_map(
                static fn ($mutation): array => $mutation->toArray(),
                $index->schemaMutations(),
            ),
            'foreign_keys' => array_map(
                static fn ($reference): array => $reference->toArray(),
                $index->foreignKeyReferences(),
            ),
            'transaction_scopes' => array_map(
                static fn ($scope): array => $scope->toArray(),
                $index->transactionScopes(),
            ),
        ];
    }

    /**
     * @return array{schema_version: int, files: array<string, mixed>}
     */
    private function cachePayload(): array
    {
        $payload = require $this->store->path();
        self::assertIsArray($payload);
        self::assertIsInt($payload['schema_version'] ?? null);
        self::assertIsArray($payload['files'] ?? null);
        $files = [];

        foreach ($payload['files'] as $path => $file) {
            self::assertIsString($path);
            $files[$path] = $file;
        }

        return [
            'schema_version' => $payload['schema_version'],
            'files' => $files,
        ];
    }
}

final class IncrementalSourceModule extends Module
{
}

final class AlternateSourceModule extends Module
{
}

final class ConsumerSourceModule extends Module
{
}

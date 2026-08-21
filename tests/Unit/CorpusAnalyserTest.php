<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Tools\Corpus\CorpusAnalyser;

require_once dirname(__DIR__, 2).'/tools/corpus/CorpusAnalyser.php';

final class CorpusAnalyserTest extends TestCase
{
    private ?string $temporaryPath = null;

    protected function tearDown(): void
    {
        if ($this->temporaryPath !== null) {
            (new Filesystem)->deleteDirectory($this->temporaryPath);
        }

        parent::tearDown();
    }

    public function test_it_runs_independent_oracles_against_a_disposable_laravel_corpus(): void
    {
        $this->temporaryPath = sys_get_temp_dir().'/moduark-corpus-test-'.bin2hex(random_bytes(6));
        $this->write('app/User/UserService.php', <<<'PHP'
<?php

namespace App\User;

final class UserService
{
}
PHP);
        $this->write('app/Order/OrderService.php', <<<'PHP'
<?php

namespace App\Order;

use App\User\UserService;
use Illuminate\Support\Facades\DB as Database;

final class OrderService
{
    public function __construct(UserService $users)
    {
    }

    public function query(string $dynamic): void
    {
        // Database::table('comment_only');
        Database::table(
            'orders',
        )->leftJoin(
            'users',
            'users.id',
            '=',
            'orders.user_id',
        );
        Database::table($dynamic);
    }
}
PHP);
        $this->write('database/migrations/2026_08_21_000000_create_tables.php', <<<'PHP'
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('users', static function (Blueprint $table): void {});
Schema::create('orders', static function (Blueprint $table): void {});
PHP);
        /** @var array{
         *     schema: 1,
         *     name: non-empty-string,
         *     source_roots: non-empty-list<array{
         *         path: string,
         *         namespace: string,
         *         group_by: 'first-directory'|'single',
         *         group?: string
         *     }>,
         *     command_root: array{path: string, namespace: string}
         * } $manifest
         */
        $manifest = [
            'schema' => 1,
            'name' => 'Test corpus',
            'source_roots' => [
                [
                    'path' => 'app',
                    'namespace' => 'App',
                    'group_by' => 'first-directory',
                ],
                [
                    'path' => 'database/migrations',
                    'namespace' => 'Database\\Migrations',
                    'group_by' => 'single',
                    'group' => 'Migrations',
                ],
            ],
            'command_root' => [
                'path' => 'app',
                'namespace' => 'App',
            ],
        ];

        $root = $this->temporaryPath;
        self::assertNotNull($root);
        $result = (new CorpusAnalyser)->analyse($manifest, $root);
        $corpus = $result['corpus'];
        $analysis = $result['analysis'];
        $oracles = $result['oracles'];
        $commands = $result['command_discovery'];
        self::assertIsArray($corpus);
        self::assertIsArray($analysis);
        self::assertIsArray($oracles);
        self::assertIsArray($commands);
        self::assertSame(3, $corpus['analysed_php_files']);
        self::assertSame(['app' => 2, 'database/migrations' => 1], $corpus['source_counts']);
        self::assertSame(2, $analysis['symbols']);
        self::assertSame(1, $analysis['class_references']);
        self::assertSame(1, $analysis['cross_module_pairs']);
        self::assertSame(1, $analysis['undeclared_dependency_violations']);
        self::assertSame(3, $analysis['table_accesses']);
        self::assertSame(2, $analysis['schema_mutations']);
        $unresolved = $analysis['unresolved_evidence'];
        self::assertIsArray($unresolved);
        self::assertSame(1, $unresolved['unique_locations']);
        $precision = $oracles['precision'];
        $anchoring = $oracles['anchoring'];
        $recall = $oracles['literal_facade_recall'];
        self::assertIsArray($precision);
        self::assertIsArray($anchoring);
        self::assertIsArray($recall);
        self::assertSame([], $precision['misses']);
        self::assertSame(0, $anchoring['collision_locations']);
        self::assertSame(4, $recall['expected']);
        self::assertSame(4, $recall['matched']);
        self::assertSame([], $recall['misses']);
        self::assertSame('passed', $commands['status']);
    }

    private function write(string $relativePath, string $contents): void
    {
        self::assertNotNull($this->temporaryPath);
        $path = $this->temporaryPath.'/'.$relativePath;
        $directory = dirname($path);

        if (! is_dir($directory)) {
            self::assertTrue(mkdir($directory, 0755, true));
        }

        self::assertNotFalse(file_put_contents($path, $contents));
    }
}

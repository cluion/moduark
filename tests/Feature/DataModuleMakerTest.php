<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Generation\ModuleMakerTargetResolver;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use FilesystemIterator;
use Illuminate\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Tests\TestCase;

final class DataModuleMakerTest extends TestCase
{
    private string $temporaryBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryBasePath = sys_get_temp_dir().'/moduark-data-maker-'.bin2hex(random_bytes(8));

        self::assertTrue(mkdir($this->temporaryBasePath.'/app/Modules/User', 0755, true));
        self::assertIsInt(file_put_contents(
            $this->temporaryBasePath.'/composer.json',
            json_encode([
                'autoload' => [
                    'psr-4' => [
                        'MakerFixture\\' => 'app/',
                    ],
                ],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        ));
        self::assertIsInt(file_put_contents(
            $this->temporaryBasePath.'/app/Modules/User/UserModule.php',
            "<?php\n",
        ));

        $this->application()->setBasePath($this->temporaryBasePath);
        $registry = new ModuleRegistry([
            new DiscoveredModule(
                'User',
                Module::class,
                $this->temporaryBasePath.'/app/Modules/User/UserModule.php',
                'MakerFixture\\Modules\\User',
            ),
        ]);
        $this->application()->instance(
            ModuleMakerTargetResolver::class,
            new ModuleMakerTargetResolver($this->application(), $registry),
        );
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->temporaryBasePath);

        parent::tearDown();
    }

    public function test_data_plans_match_the_reviewed_laravel_major_fixture(): void
    {
        $major = (int) explode('.', Application::VERSION, 2)[0];
        $fixture = dirname(__DIR__).'/Fixtures/Generation/data-types-laravel-'.$major.'.json';
        $expected = $this->planFixture($fixture);

        self::assertSame(1, $expected['schema']);
        self::assertSame($major, $expected['laravel_major']);

        foreach ($expected['plans'] as $plan) {
            $command = trim(sprintf(
                'moduark:make User %s %s %s --dry-run',
                $plan['type'],
                $plan['name'],
                $plan['options'],
            ));

            if ($plan['target_suffix'] !== null) {
                $this->command($command)
                    ->expectsOutputToContain($plan['target_suffix'])
                    ->assertSuccessful();

                continue;
            }

            $this->command($command)
                ->expectsOutputToContain('CREATE '.$plan['target'])
                ->assertSuccessful();
        }

        self::assertSame(
            [$this->temporaryBasePath.'/app/Modules/User/UserModule.php'],
            $this->files(),
        );
        self::assertDirectoryDoesNotExist($this->temporaryBasePath.'/database');
    }

    public function test_it_generates_module_owned_factories_and_seeders(): void
    {
        $this->command('moduark:make User factory Admin/Profile')->assertSuccessful();
        $this->command(
            'moduark:make User factory Profile/AccountProfileFactory --model=Account/Profile',
        )->assertSuccessful();
        $this->command('moduark:make User seeder Demo/ProfileSeeder')->assertSuccessful();

        $inferredPath = $this->temporaryBasePath
            .'/app/Modules/User/Database/Factories/Admin/ProfileFactory.php';
        $explicitPath = $this->temporaryBasePath
            .'/app/Modules/User/Database/Factories/Profile/AccountProfileFactory.php';
        $seederPath = $this->temporaryBasePath
            .'/app/Modules/User/Database/Seeders/Demo/ProfileSeeder.php';
        $inferred = (string) file_get_contents($inferredPath);
        $explicit = (string) file_get_contents($explicitPath);
        $seeder = (string) file_get_contents($seederPath);

        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Database\\Factories\\Admin;',
            $inferred,
        );
        self::assertStringContainsString(
            'use MakerFixture\\Modules\\User\\Models\\Admin\\Profile;',
            $inferred,
        );
        self::assertStringContainsString('class ProfileFactory extends Factory', $inferred);
        self::assertStringContainsString('protected $model = Profile::class;', $inferred);
        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Database\\Factories\\Profile;',
            $explicit,
        );
        self::assertStringContainsString(
            'use MakerFixture\\Modules\\User\\Models\\Account\\Profile;',
            $explicit,
        );
        self::assertStringContainsString('class AccountProfileFactory extends Factory', $explicit);
        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Database\\Seeders\\Demo;',
            $seeder,
        );
        self::assertStringContainsString('use Illuminate\\Database\\Seeder;', $seeder);
        self::assertStringContainsString('class ProfileSeeder extends Seeder', $seeder);
        self::assertStringContainsString('public function run(): void', $seeder);
        self::assertSame([
            $inferredPath,
            $explicitPath,
            $seederPath,
            $this->temporaryBasePath.'/app/Modules/User/UserModule.php',
        ], $this->files());
        self::assertDirectoryDoesNotExist($this->temporaryBasePath.'/database');
    }

    public function test_it_generates_plain_and_model_bound_module_owned_observers(): void
    {
        $this->command('moduark:make User observer Audit/ProfileObserver')->assertSuccessful();
        $this->command(
            'moduark:make User observer Profile/ProfileObserver --model=Account/Profile',
        )->assertSuccessful();

        $plainPath = $this->temporaryBasePath
            .'/app/Modules/User/Observers/Audit/ProfileObserver.php';
        $modelPath = $this->temporaryBasePath
            .'/app/Modules/User/Observers/Profile/ProfileObserver.php';
        $plain = (string) file_get_contents($plainPath);
        $model = (string) file_get_contents($modelPath);

        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Observers\\Audit;',
            $plain,
        );
        self::assertStringContainsString('class ProfileObserver', $plain);
        self::assertStringNotContainsString('function created(', $plain);
        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Observers\\Profile;',
            $model,
        );
        self::assertStringContainsString(
            'use MakerFixture\\Modules\\User\\Models\\Account\\Profile;',
            $model,
        );
        self::assertStringContainsString('public function created(Profile $profile): void', $model);
        self::assertStringContainsString('public function forceDeleted(Profile $profile): void', $model);
        self::assertSame([
            $plainPath,
            $modelPath,
            $this->temporaryBasePath.'/app/Modules/User/UserModule.php',
        ], $this->files());
        self::assertDirectoryDoesNotExist($this->temporaryBasePath.'/app/Observers');
    }

    public function test_it_generates_module_owned_create_update_and_plain_migrations(): void
    {
        $this->command('moduark:make User migration CreateProfilesTable')->assertSuccessful();
        $this->command('moduark:make User migration AddStatusToProfilesTable')->assertSuccessful();
        $this->command('moduark:make User migration RecalculateProfiles')->assertSuccessful();

        $create = $this->oneMigration('*_create_profiles_table.php');
        $update = $this->oneMigration('*_add_status_to_profiles_table.php');
        $plain = $this->oneMigration('*_recalculate_profiles.php');

        self::assertStringContainsString("Schema::create('profiles'", $create);
        self::assertStringContainsString("Schema::dropIfExists('profiles')", $create);
        self::assertStringContainsString("Schema::table('profiles'", $update);
        self::assertStringNotContainsString('Schema::', $plain);
        self::assertDirectoryDoesNotExist($this->temporaryBasePath.'/database');
    }

    public function test_migration_explicit_modes_override_name_inference(): void
    {
        $this->command(
            'moduark:make User migration RebuildAuditLog --create=audit_logs',
        )->assertSuccessful();
        $this->command(
            'moduark:make User migration CreateLegacyProfiles --table=profiles',
        )->assertSuccessful();

        self::assertStringContainsString(
            "Schema::create('audit_logs'",
            $this->oneMigration('*_rebuild_audit_log.php'),
        );
        self::assertStringContainsString(
            "Schema::table('profiles'",
            $this->oneMigration('*_create_legacy_profiles.php'),
        );
    }

    public function test_migration_refuses_duplicate_name_and_force_without_mutation(): void
    {
        $this->command('moduark:make User migration CreateProfilesTable')->assertSuccessful();
        $path = $this->oneMigrationPath('*_create_profiles_table.php');
        self::assertIsInt(file_put_contents($path, 'existing migration'));

        $this->command('moduark:make User migration CreateProfilesTable')
            ->expectsOutputToContain('Migration already exists.')
            ->assertFailed();
        $this->command('moduark:make User migration CreateProfilesTable --force')
            ->expectsOutputToContain(
                'Module Maker failed: The --force option is not supported for Maker type [migration].',
            )
            ->assertExitCode(2);

        self::assertSame('existing migration', file_get_contents($path));
    }

    public function test_migration_rejects_conflicting_invalid_or_foreign_options_without_mutation(): void
    {
        $this->command(
            'moduark:make User migration CreateProfilesTable --create=profiles --table=profiles',
        )->expectsOutputToContain(
            'Module Maker failed: The migration Maker options [--create, --table] cannot be combined.',
        )->assertExitCode(2);
        $this->command('moduark:make User migration CreateProfilesTable --create=Profile-Items')
            ->expectsOutputToContain(
                'Module Maker failed: Migration table [Profile-Items] must be a lowercase snake_case database identifier.',
            )
            ->assertExitCode(2);
        $this->command('moduark:make User migration Admin/CreateProfilesTable')
            ->expectsOutputToContain(
                'Module Maker failed: Migration name [Admin\\CreateProfilesTable] must be one StudlyCase segment without a namespace.',
            )
            ->assertExitCode(2);
        $this->command('moduark:make User migration CreateProfilesTable --model=Profile')
            ->expectsOutputToContain(
                'Module Maker failed: The --model option is not supported for Maker type [migration].',
            )
            ->assertExitCode(2);

        self::assertSame(
            [$this->temporaryBasePath.'/app/Modules/User/UserModule.php'],
            $this->files(),
        );
    }

    public function test_observer_preserves_native_collision_and_force_behavior(): void
    {
        $path = $this->temporaryBasePath
            .'/app/Modules/User/Observers/ProfileObserver.php';

        $this->command('moduark:make User observer ProfileObserver')->assertSuccessful();
        self::assertIsInt(file_put_contents($path, 'existing observer'));

        $this->command('moduark:make User observer ProfileObserver')
            ->expectsOutputToContain('Observer already exists.')
            ->assertFailed();
        self::assertSame('existing observer', file_get_contents($path));

        $this->command('moduark:make User observer ProfileObserver --force')->assertSuccessful();
        self::assertNotSame('existing observer', file_get_contents($path));
    }

    public function test_observer_rejects_foreign_or_invalid_options_without_mutation(): void
    {
        $this->command('moduark:make User observer ProfileObserver --guard=web')
            ->expectsOutputToContain(
                'Module Maker failed: The --guard option is not supported for Maker type [observer].',
            )
            ->assertExitCode(2);
        $this->command('moduark:make User observer ProfileObserver --model=profile')
            ->expectsOutputToContain(
                'Module Maker failed: Observer model [profile] must contain one or more StudlyCase class segments relative to the Module Models namespace.',
            )
            ->assertExitCode(2);
        $this->command('moduark:make User observer ProfileObserver --model=/App/Models/Profile')
            ->expectsOutputToContain(
                'must contain one or more StudlyCase class segments relative to the Module Models namespace.',
            )
            ->assertExitCode(2);
        $this->command('moduark:make User observer ProfileObserver --model=')
            ->expectsOutputToContain(
                'Module Maker failed: The --model option must be a non-empty string when provided.',
            )
            ->assertExitCode(2);

        self::assertSame(
            [$this->temporaryBasePath.'/app/Modules/User/UserModule.php'],
            $this->files(),
        );
        self::assertDirectoryDoesNotExist($this->temporaryBasePath.'/app/Observers');
    }

    public function test_factory_and_seeder_preserve_collision_and_native_force_contracts(): void
    {
        $factoryPath = $this->temporaryBasePath
            .'/app/Modules/User/Database/Factories/ProfileFactory.php';
        $seederPath = $this->temporaryBasePath
            .'/app/Modules/User/Database/Seeders/ProfileSeeder.php';

        $this->command('moduark:make User factory ProfileFactory')->assertSuccessful();
        $this->command('moduark:make User seeder ProfileSeeder')->assertSuccessful();
        self::assertIsInt(file_put_contents($factoryPath, 'existing factory'));
        self::assertIsInt(file_put_contents($seederPath, 'existing seeder'));

        $this->command('moduark:make User factory ProfileFactory')
            ->expectsOutputToContain('Factory already exists.')
            ->assertFailed();
        $this->command('moduark:make User seeder ProfileSeeder')
            ->expectsOutputToContain('Seeder already exists.')
            ->assertFailed();
        $this->command('moduark:make User factory ProfileFactory --force')
            ->expectsOutputToContain(
                'Module Maker failed: The --force option is not supported for Maker type [factory].',
            )
            ->assertExitCode(2);
        $this->command('moduark:make User seeder ProfileSeeder --force')
            ->expectsOutputToContain(
                'Module Maker failed: The --force option is not supported for Maker type [seeder].',
            )
            ->assertExitCode(2);

        self::assertSame('existing factory', file_get_contents($factoryPath));
        self::assertSame('existing seeder', file_get_contents($seederPath));
    }

    public function test_data_makers_reject_foreign_or_invalid_options_without_mutation(): void
    {
        $this->command('moduark:make User factory ProfileFactory --guard=web')
            ->expectsOutputToContain(
                'Module Maker failed: The --guard option is not supported for Maker type [factory].',
            )
            ->assertExitCode(2);
        $this->command('moduark:make User seeder ProfileSeeder --model=Profile')
            ->expectsOutputToContain(
                'Module Maker failed: The --model option is not supported for Maker type [seeder].',
            )
            ->assertExitCode(2);
        $this->command('moduark:make User factory ProfileFactory --model=profile')
            ->expectsOutputToContain(
                'Module Maker failed: Factory model [profile] must contain one or more StudlyCase class segments relative to the Module Models namespace.',
            )
            ->assertExitCode(2);
        $this->command('moduark:make User factory ProfileFactory --model=/App/Models/Profile')
            ->expectsOutputToContain(
                'must contain one or more StudlyCase class segments relative to the Module Models namespace.',
            )
            ->assertExitCode(2);
        $this->command('moduark:make User factory ProfileFactory --model=')
            ->expectsOutputToContain(
                'Module Maker failed: The --model option must be a non-empty string when provided.',
            )
            ->assertExitCode(2);
        $this->command('moduark:make User factory Factory')
            ->expectsOutputToContain(
                'Module Maker failed: Factory name [Factory] must identify a model before the Factory suffix.',
            )
            ->assertExitCode(2);

        self::assertSame(
            [$this->temporaryBasePath.'/app/Modules/User/UserModule.php'],
            $this->files(),
        );
        self::assertDirectoryDoesNotExist($this->temporaryBasePath.'/database');
    }

    /** @return list<string> */
    private function files(): array
    {
        /** @var list<string> $files */
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $this->temporaryBasePath.'/app/Modules/User',
                FilesystemIterator::SKIP_DOTS,
            ),
        );

        /** @var SplFileInfo $entry */
        foreach ($iterator as $entry) {
            if ($entry->isFile()) {
                $files[] = $entry->getPathname();
            }
        }

        sort($files, SORT_STRING);

        return $files;
    }

    private function oneMigration(string $pattern): string
    {
        return (string) file_get_contents($this->oneMigrationPath($pattern));
    }

    private function oneMigrationPath(string $pattern): string
    {
        $matches = glob(
            $this->temporaryBasePath.'/app/Modules/User/Database/Migrations/'.$pattern,
        );

        self::assertIsArray($matches);
        self::assertCount(1, $matches);

        return $matches[0];
    }

    /**
     * @return array{
     *     schema: int,
     *     laravel_major: int,
     *     plans: list<array{type: string, name: string, options: string, target: string, target_suffix: ?string}>
     * }
     */
    private function planFixture(string $path): array
    {
        $fixture = json_decode(
            (string) file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        if (
            ! is_array($fixture)
            || ! is_int($fixture['schema'] ?? null)
            || ! is_int($fixture['laravel_major'] ?? null)
            || ! is_array($fixture['plans'] ?? null)
        ) {
            throw new RuntimeException("Data plan fixture [{$path}] has an invalid root shape.");
        }

        $plans = [];

        foreach ($fixture['plans'] as $plan) {
            if (
                ! is_array($plan)
                || ! is_string($plan['type'] ?? null)
                || ! is_string($plan['name'] ?? null)
                || ! is_string($plan['options'] ?? null)
                || ! is_string($plan['target'] ?? null)
                || (isset($plan['target_suffix']) && ! is_string($plan['target_suffix']))
            ) {
                throw new RuntimeException("Data plan fixture [{$path}] has an invalid plan.");
            }

            $plans[] = [
                'type' => $plan['type'],
                'name' => $plan['name'],
                'options' => $plan['options'],
                'target' => $plan['target'],
                'target_suffix' => $plan['target_suffix'] ?? null,
            ];
        }

        return [
            'schema' => $fixture['schema'],
            'laravel_major' => $fixture['laravel_major'],
            'plans' => $plans,
        ];
    }
}

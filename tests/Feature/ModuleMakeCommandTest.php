<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Generation\GenerationExecutor;
use Cluion\Moduark\Generation\ModuleMakerTargetResolver;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use FilesystemIterator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Filesystem\Filesystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Tests\TestCase;

final class ModuleMakeCommandTest extends TestCase
{
    private string $temporaryBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryBasePath = sys_get_temp_dir().'/moduark-module-maker-'.bin2hex(random_bytes(8));

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
        $this->useModule(
            $this->temporaryBasePath.'/app/Modules/User/UserModule.php',
            'MakerFixture\\Modules\\User',
        );
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->temporaryBasePath);

        parent::tearDown();
    }

    public function test_it_generates_a_model_inside_an_existing_module(): void
    {
        $path = $this->temporaryBasePath.'/app/Modules/User/Models/Admin/Profile.php';

        $this->command('moduark:make user model Admin/Profile')->assertSuccessful();

        self::assertFileExists($path);
        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Models\\Admin;',
            (string) file_get_contents($path),
        );
    }

    public function test_it_generates_an_invokable_controller_with_native_stubs(): void
    {
        $path = $this->temporaryBasePath.'/app/Modules/User/Http/Controllers/ProfileController.php';

        $this->command('moduark:make User controller ProfileController --invokable')->assertSuccessful();

        $source = (string) file_get_contents($path);

        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Http\\Controllers;',
            $source,
        );
        self::assertStringContainsString('public function __invoke(', $source);
    }

    public function test_it_generates_an_api_resource_controller_without_related_files(): void
    {
        $path = $this->temporaryBasePath.'/app/Modules/User/Http/Controllers/ProfileController.php';

        $this->command('moduark:make User controller ProfileController --resource --api')
            ->assertSuccessful();

        $source = (string) file_get_contents($path);

        self::assertStringContainsString('public function index()', $source);
        self::assertStringContainsString('public function store(Request $request)', $source);
        self::assertStringNotContainsString('public function create()', $source);
        self::assertStringNotContainsString('public function edit(', $source);
        self::assertSame([$path, $this->temporaryBasePath.'/app/Modules/User/UserModule.php'], $this->files());
    }

    public function test_force_preserves_native_overwrite_behavior(): void
    {
        $path = $this->temporaryBasePath.'/app/Modules/User/Models/Profile.php';

        $this->command('moduark:make User model Profile')->assertSuccessful();
        self::assertIsInt(file_put_contents($path, 'existing source'));

        $this->command('moduark:make User model Profile')
            ->expectsOutputToContain('Model already exists.')
            ->assertFailed();
        self::assertSame('existing source', file_get_contents($path));

        $this->command('moduark:make User model Profile --force')->assertSuccessful();
        self::assertNotSame('existing source', file_get_contents($path));
    }

    public function test_dry_run_renders_the_model_plan_without_filesystem_mutation(): void
    {
        $path = $this->temporaryBasePath.'/app/Modules/User/Models/Admin/Profile.php';

        $this->command('moduark:make User model Admin/Profile --dry-run')
            ->expectsOutputToContain('Generation plan (dry run):')
            ->expectsOutputToContain('CREATE Models/Admin/Profile.php')
            ->assertSuccessful();

        self::assertFileDoesNotExist($path);
        self::assertSame(
            [$this->temporaryBasePath.'/app/Modules/User/UserModule.php'],
            $this->files(),
        );
    }

    public function test_dry_run_validates_controller_options_without_writing_files(): void
    {
        $path = $this->temporaryBasePath.'/app/Modules/User/Http/Controllers/ProfileController.php';

        $this->command(
            'moduark:make User controller ProfileController --resource --api --dry-run',
        )
            ->expectsOutputToContain('CREATE Http/Controllers/ProfileController.php')
            ->assertSuccessful();

        self::assertFileDoesNotExist($path);
    }

    public function test_dry_run_uses_the_same_collision_preflight_as_execution(): void
    {
        $path = $this->temporaryBasePath.'/app/Modules/User/Models/Profile.php';

        $this->command('moduark:make User model Profile')->assertSuccessful();
        $source = (string) file_get_contents($path);

        $this->command('moduark:make User model Profile --dry-run')
            ->expectsOutputToContain('Model already exists.')
            ->assertFailed();
        self::assertSame($source, file_get_contents($path));

        $this->command('moduark:make User model Profile --force --dry-run')
            ->expectsOutputToContain('OVERWRITE Models/Profile.php')
            ->assertSuccessful();
        self::assertSame($source, file_get_contents($path));
    }

    public function test_it_dry_runs_a_complete_model_factory_and_migration_plan_without_writes(): void
    {
        $this->command(
            'moduark:make User model Admin/Profile --factory --migration --dry-run',
        )
            ->expectsOutputToContain('CREATE Models/Admin/Profile.php')
            ->expectsOutputToContain('CREATE Database/Factories/Admin/ProfileFactory.php')
            ->expectsOutputToContain('CREATE Database/Migrations/')
            ->assertSuccessful();

        self::assertSame(
            [$this->temporaryBasePath.'/app/Modules/User/UserModule.php'],
            $this->files(),
        );
    }

    public function test_it_generates_a_module_owned_model_factory_and_migration_atomically(): void
    {
        $model = $this->temporaryBasePath.'/app/Modules/User/Models/Admin/Profile.php';
        $factory = $this->temporaryBasePath
            .'/app/Modules/User/Database/Factories/Admin/ProfileFactory.php';

        $this->command(
            'moduark:make User model Admin/Profile --factory --migration',
        )->assertSuccessful();

        self::assertFileExists($model);
        self::assertFileExists($factory);
        self::assertStringContainsString(
            'return ProfileFactory::new();',
            (string) file_get_contents($model),
        );
        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Database\\Factories\\Admin;',
            (string) file_get_contents($factory),
        );
        self::assertStringContainsString(
            'use MakerFixture\\Modules\\User\\Models\\Admin\\Profile;',
            (string) file_get_contents($factory),
        );

        $migrations = glob(
            $this->temporaryBasePath
                .'/app/Modules/User/Database/Migrations/*_create_profiles_table.php',
        );
        self::assertIsArray($migrations);
        self::assertCount(1, $migrations);
        self::assertStringContainsString(
            "Schema::create('profiles'",
            (string) file_get_contents($migrations[0]),
        );

        require_once $factory;
        require_once $model;

        $factoryClass = 'MakerFixture\\Modules\\User\\Database\\Factories\\Admin\\ProfileFactory';
        self::assertTrue(class_exists($factoryClass));
        $factoryInstance = ('MakerFixture\\Modules\\User\\Models\\Admin\\Profile')::factory();
        self::assertInstanceOf(Factory::class, $factoryInstance);
        self::assertSame($factoryClass, get_debug_type($factoryInstance));
        self::assertSame(
            'MakerFixture\\Modules\\User\\Models\\Admin\\Profile',
            get_debug_type($factoryInstance->make()),
        );
    }

    public function test_composite_preflight_reports_all_collisions_without_partial_writes(): void
    {
        $root = $this->temporaryBasePath.'/app/Modules/User';
        $model = $root.'/Models/Profile.php';
        $factory = $root.'/Database/Factories/ProfileFactory.php';
        $migration = $root.'/Database/Migrations/2026_08_23_000000_create_profiles_table.php';

        foreach ([$model, $factory, $migration] as $path) {
            self::assertTrue(is_dir(dirname($path)) || mkdir(dirname($path), 0755, true));
            self::assertIsInt(file_put_contents($path, 'existing'));
        }

        $this->command('moduark:make User model Profile --factory --migration')
            ->expectsOutputToContain('Model already exists.')
            ->expectsOutputToContain('Factory already exists.')
            ->expectsOutputToContain('Migration already exists.')
            ->assertFailed();

        foreach ([$model, $factory, $migration] as $path) {
            self::assertSame('existing', file_get_contents($path));
        }
    }

    public function test_composite_write_failure_rolls_back_every_created_target(): void
    {
        $root = $this->temporaryBasePath.'/app/Modules/User';
        $filesystem = new class extends Filesystem
        {
            public function replace($path, $content, $mode = null): void
            {
                if (str_contains((string) $path, '/Database/Migrations/')) {
                    throw new RuntimeException('Injected migration write failure.');
                }

                parent::replace($path, $content, $mode);
            }
        };
        $this->application()->instance(
            GenerationExecutor::class,
            new GenerationExecutor($filesystem),
        );

        $this->command('moduark:make User model Profile --factory --migration')
            ->expectsOutputToContain('Module Maker failed: Injected migration write failure.')
            ->expectsOutputToContain(
                'Generation failed; all planned filesystem changes were rolled back.',
            )
            ->assertExitCode(ExitPolicy::TOOL_ERROR);

        self::assertFileDoesNotExist($root.'/Models/Profile.php');
        self::assertFileDoesNotExist($root.'/Database/Factories/ProfileFactory.php');
        self::assertSame([$root.'/UserModule.php'], $this->files());
    }

    public function test_it_rejects_ambiguous_existing_migrations_before_writing_the_model(): void
    {
        $root = $this->temporaryBasePath.'/app/Modules/User';
        $directory = $root.'/Database/Migrations';
        self::assertTrue(mkdir($directory, 0755, true));

        foreach (['2026_08_22_000000', '2026_08_23_000000'] as $timestamp) {
            self::assertIsInt(file_put_contents(
                $directory.'/'.$timestamp.'_create_profiles_table.php',
                'existing',
            ));
        }

        $this->command('moduark:make User model Profile --migration --force')
            ->expectsOutputToContain(
                'Module Maker failed: Migration [create_profiles_table] has multiple Module targets:',
            )
            ->assertExitCode(ExitPolicy::TOOL_ERROR);

        self::assertFileDoesNotExist($root.'/Models/Profile.php');
    }

    public function test_it_rejects_model_composite_options_for_controllers(): void
    {
        $this->command('moduark:make User controller ProfileController --factory')
            ->expectsOutputToContain(
                'Module Maker failed: The --factory option is not supported for Maker type [controller].',
            )
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    public function test_it_rejects_an_unknown_module(): void
    {
        $this->command('moduark:make Unknown model Profile')
            ->expectsOutputToContain('Module Maker failed: Module [Unknown] was not found.')
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    public function test_it_rejects_an_unsupported_maker_type(): void
    {
        $this->command('moduark:make User verification ProfileView')
            ->expectsOutputToContain(
                'Module Maker failed: Maker type [verification] is not supported; expected cast, channel, class, component, controller, enum, event, exception, factory, interface, job, job-middleware, listener, mail, middleware, migration, model, notification, observer, policy, request, resource, rule, scope, seeder, test, trait, or view.',
            )
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    public function test_it_rejects_unsafe_class_names(): void
    {
        $this->command('moduark:make User model ../Profile')
            ->expectsOutputToContain(
                'Module Maker failed: Maker name [../Profile] must contain one or more StudlyCase class segments.',
            )
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    public function test_it_rejects_php_reserved_class_names_after_qualification(): void
    {
        $this->command('moduark:make User model Admin/Class')
            ->expectsOutputToContain(
                'Module Maker failed: Maker class name [Class] is reserved by PHP.',
            )
            ->assertExitCode(ExitPolicy::TOOL_ERROR);

        self::assertFileDoesNotExist(
            $this->temporaryBasePath.'/app/Modules/User/Models/Admin/Class.php',
        );
    }

    public function test_it_rejects_controller_options_for_models(): void
    {
        $this->command('moduark:make User model Profile --invokable')
            ->expectsOutputToContain(
                'Module Maker failed: The --invokable option is not supported for Maker type [model].',
            )
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    public function test_it_rejects_conflicting_controller_modes(): void
    {
        $this->command('moduark:make User controller ProfileController --invokable --resource')
            ->expectsOutputToContain(
                'Module Maker failed: The controller Maker options [--invokable, --resource] cannot be combined.',
            )
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    public function test_it_rejects_a_module_outside_the_laravel_application_path(): void
    {
        $path = $this->temporaryBasePath.'/domain/Modules/User/UserModule.php';

        self::assertTrue(mkdir(dirname($path), 0755, true));
        self::assertIsInt(file_put_contents($path, "<?php\n"));
        $this->useModule($path, 'Domain\\Modules\\User');

        $this->command('moduark:make User model Profile')
            ->expectsOutputToContain(
                'must be inside Laravel application path',
            )
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    public function test_it_rejects_a_module_namespace_that_does_not_match_its_path(): void
    {
        $this->useModule(
            $this->temporaryBasePath.'/app/Modules/User/UserModule.php',
            'Wrong\\Modules\\User',
        );

        $this->command('moduark:make User model Profile')
            ->expectsOutputToContain(
                'Module Maker failed: Module [User] namespace [Wrong\\Modules\\User]'
                    .' must match application path namespace [MakerFixture\\Modules\\User] for moduark:make.',
            )
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    public function test_it_reports_an_unresolvable_application_namespace_as_a_tool_error(): void
    {
        self::assertIsInt(file_put_contents($this->temporaryBasePath.'/composer.json', "{}\n"));

        $this->command('moduark:make User model Profile')
            ->expectsOutputToContain(
                'Module Maker failed: The Laravel application namespace could not be resolved.',
            )
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    private function useModule(string $path, string $namespace): void
    {
        $registry = new ModuleRegistry([
            new DiscoveredModule('User', Module::class, $path, $namespace),
        ]);

        $this->application()->instance(
            ModuleMakerTargetResolver::class,
            new ModuleMakerTargetResolver($this->application(), $registry),
        );
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
}

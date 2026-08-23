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

    /**
     * @return array{
     *     schema: int,
     *     laravel_major: int,
     *     plans: list<array{type: string, name: string, options: string, target: string}>
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
            ) {
                throw new RuntimeException("Data plan fixture [{$path}] has an invalid plan.");
            }

            $plans[] = [
                'type' => $plan['type'],
                'name' => $plan['name'],
                'options' => $plan['options'],
                'target' => $plan['target'],
            ];
        }

        return [
            'schema' => $fixture['schema'],
            'laravel_major' => $fixture['laravel_major'],
            'plans' => $plans,
        ];
    }
}

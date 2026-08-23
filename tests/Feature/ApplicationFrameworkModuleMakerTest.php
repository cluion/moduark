<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Generation\GeneratorDescriptor;
use Cluion\Moduark\Generation\GeneratorRegistry;
use Cluion\Moduark\Generation\ModuleMakerTargetResolver;
use Cluion\Moduark\Generation\ModuleMakerType;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use Cluion\Moduark\Resources\ModuleResourceDiscoverer;
use Composer\Autoload\ClassLoader;
use FilesystemIterator;
use Illuminate\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use ParseError;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Tests\TestCase;

final class ApplicationFrameworkModuleMakerTest extends TestCase
{
    private string $temporaryBasePath;

    private ClassLoader $loader;

    private DiscoveredModule $module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryBasePath = sys_get_temp_dir().'/moduark-application-maker-'.bin2hex(random_bytes(8));
        $modulePath = $this->temporaryBasePath.'/app/Modules/User';

        self::assertTrue(mkdir($modulePath, 0755, true));
        self::assertTrue(mkdir($this->temporaryBasePath.'/bootstrap', 0755, true));
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
        self::assertIsInt(file_put_contents($modulePath.'/UserModule.php', "<?php\n"));
        self::assertIsInt(file_put_contents(
            $this->temporaryBasePath.'/bootstrap/providers.php',
            "<?php\n\nreturn ['application-provider-marker'];\n",
        ));

        $this->loader = new ClassLoader($this->temporaryBasePath.'/vendor');
        $this->loader->addPsr4('MakerFixture\\', $this->temporaryBasePath.'/app');
        $this->loader->register(true);
        $this->application()->setBasePath($this->temporaryBasePath);
        $this->module = new DiscoveredModule(
            'User',
            Module::class,
            $modulePath.'/UserModule.php',
            'MakerFixture\\Modules\\User',
        );
        $registry = new ModuleRegistry([$this->module]);
        $this->application()->instance(
            ModuleMakerTargetResolver::class,
            new ModuleMakerTargetResolver($this->application(), $registry),
        );
    }

    protected function tearDown(): void
    {
        $this->loader->unregister();
        (new Filesystem)->deleteDirectory($this->temporaryBasePath);

        parent::tearDown();
    }

    public function test_application_framework_plans_match_the_reviewed_laravel_major_fixture(): void
    {
        $major = (int) explode('.', Application::VERSION, 2)[0];
        $fixture = dirname(__DIR__).'/Fixtures/Generation/application-framework-laravel-'.$major.'.json';
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

            $pending = $this->command($command);

            foreach ($plan['targets'] as $target) {
                $pending->expectsOutputToContain('CREATE '.$target);
            }

            $pending->assertSuccessful();
        }

        self::assertSame(
            [
                $this->temporaryBasePath.'/app/Modules/User/UserModule.php',
            ],
            $this->moduleFiles(),
        );
    }

    public function test_it_generates_module_owned_command_config_and_provider_without_global_mutation(): void
    {
        $bootstrapProviders = (string) file_get_contents(
            $this->temporaryBasePath.'/bootstrap/providers.php',
        );

        $this->command(
            'moduark:make User command SyncOrders --command=orders:sync --test --phpunit',
        )->assertSuccessful();
        $this->command('moduark:make User config billing/services')->assertSuccessful();
        $this->command(
            'moduark:make User provider Billing/BillingServiceProvider',
        )->assertSuccessful();

        $commandPath = $this->path('Console/Commands/SyncOrders.php');
        $testPath = $this->path('Tests/Feature/Console/Commands/SyncOrdersTest.php');
        $configPath = $this->path('config/billing/services.php');
        $providerPath = $this->path('Providers/Billing/BillingServiceProvider.php');

        foreach ([$commandPath, $testPath, $configPath, $providerPath] as $path) {
            self::assertFileExists($path);
            $this->assertValidPhp($path);
        }

        $command = (string) file_get_contents($commandPath);
        $provider = (string) file_get_contents($providerPath);

        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Console\\Commands;',
            $command,
        );
        self::assertStringContainsString('orders:sync', $command);
        self::assertStringContainsString('class SyncOrders extends Command', $command);
        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Providers\\Billing;',
            $provider,
        );
        self::assertStringContainsString(
            'class BillingServiceProvider extends ServiceProvider',
            $provider,
        );
        self::assertSame(
            $bootstrapProviders,
            file_get_contents($this->temporaryBasePath.'/bootstrap/providers.php'),
        );
        self::assertFileDoesNotExist($this->temporaryBasePath.'/config/billing/services.php');

        $resources = (new ModuleResourceDiscoverer)->discover($this->module, true);

        self::assertSame(
            ['MakerFixture\\Modules\\User\\Console\\Commands\\SyncOrders'],
            $resources->commands(),
        );
        self::assertSame('orders:sync', (new ($resources->commands()[0]))->getName());
    }

    #[DataProvider('makerCases')]
    public function test_application_framework_makers_share_collision_force_and_dry_run_behavior(
        string $type,
        string $name,
        string $relativePath,
        string $options,
    ): void {
        $path = $this->path($relativePath);
        $command = trim("moduark:make User {$type} {$name} {$options}");

        $this->command($command.' --dry-run')
            ->expectsOutputToContain('CREATE '.$relativePath)
            ->assertSuccessful();
        self::assertFileDoesNotExist($path);

        $this->command($command)->assertSuccessful();
        self::assertIsInt(file_put_contents($path, 'existing source'));

        $this->command($command)
            ->expectsOutputToContain(ucfirst($type).' already exists.')
            ->assertFailed();
        self::assertSame('existing source', file_get_contents($path));

        $this->command($command.' --force')->assertSuccessful();
        self::assertNotSame('existing source', file_get_contents($path));
    }

    #[DataProvider('invalidCases')]
    public function test_application_framework_makers_reject_invalid_names_and_foreign_options(
        string $command,
        string $message,
    ): void {
        $this->command($command)
            ->expectsOutputToContain('Module Maker failed: '.$message)
            ->assertExitCode(2);

        self::assertSame(
            [$this->temporaryBasePath.'/app/Modules/User/UserModule.php'],
            $this->moduleFiles(),
        );
    }

    public function test_registry_exposes_all_thirty_one_name_based_maker_candidates(): void
    {
        $registry = new GeneratorRegistry(ModuleMakerType::cases());
        $ids = array_map(
            static fn (GeneratorDescriptor $descriptor): string => $descriptor->id(),
            $registry->all(),
        );

        self::assertCount(31, $ids);
        self::assertSame([
            'cast',
            'channel',
            'class',
            'command',
            'component',
            'config',
            'controller',
            'enum',
            'event',
            'exception',
            'factory',
            'interface',
            'job',
            'job-middleware',
            'listener',
            'mail',
            'middleware',
            'migration',
            'model',
            'notification',
            'observer',
            'policy',
            'provider',
            'request',
            'resource',
            'rule',
            'scope',
            'seeder',
            'test',
            'trait',
            'view',
        ], $ids);
    }

    /** @return iterable<string, array{string, string, string, string}> */
    public static function makerCases(): iterable
    {
        yield 'command' => [
            'command',
            'SyncOrders',
            'Console/Commands/SyncOrders.php',
            '--command=orders:sync',
        ];
        yield 'config' => [
            'config',
            'billing/services',
            'config/billing/services.php',
            '',
        ];
        yield 'provider' => [
            'provider',
            'Billing/BillingServiceProvider',
            'Providers/Billing/BillingServiceProvider.php',
            '',
        ];
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidCases(): iterable
    {
        yield 'nested command' => [
            'moduark:make User command Admin/SyncOrders',
            'Command Maker name [Admin\\SyncOrders] must be one StudlyCase segment because runtime discovery is not recursive.',
        ];
        yield 'unsafe command name' => [
            'moduark:make User command SyncOrders --command=Orders:Sync',
            'Command name [Orders:Sync] must be lowercase segments separated by colons.',
        ];
        yield 'unsafe config path' => [
            'moduark:make User config ../billing',
            'Config name [../billing] must contain lowercase alphanumeric path segments with optional dashes or underscores.',
        ];
        yield 'command resource option' => [
            'moduark:make User command SyncOrders --resource',
            'The --resource option is not supported for Maker type [command].',
        ];
        yield 'config test option' => [
            'moduark:make User config billing --test',
            'The --test option is not supported for Maker type [config].',
        ];
        yield 'provider command option' => [
            'moduark:make User provider BillingServiceProvider --command=billing:boot',
            'The --command option is not supported for Maker type [provider].',
        ];
    }

    private function path(string $relativePath): string
    {
        return $this->temporaryBasePath.'/app/Modules/User/'.$relativePath;
    }

    private function assertValidPhp(string $path): void
    {
        try {
            token_get_all((string) file_get_contents($path), TOKEN_PARSE);
        } catch (ParseError $error) {
            self::fail("Generated PHP [{$path}] is invalid: {$error->getMessage()}");
        }
    }

    /** @return list<string> */
    private function moduleFiles(): array
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
     *     plans: list<array{type: string, name: string, options: string, targets: list<string>}>
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
            throw new RuntimeException("Application/framework plan fixture [{$path}] has an invalid root shape.");
        }

        $plans = [];

        foreach ($fixture['plans'] as $plan) {
            if (
                ! is_array($plan)
                || ! is_string($plan['type'] ?? null)
                || ! is_string($plan['name'] ?? null)
                || ! is_string($plan['options'] ?? null)
                || ! is_array($plan['targets'] ?? null)
            ) {
                throw new RuntimeException("Application/framework plan fixture [{$path}] has an invalid plan.");
            }

            $targets = [];

            foreach ($plan['targets'] as $target) {
                if (! is_string($target)) {
                    throw new RuntimeException("Application/framework plan fixture [{$path}] has an invalid target.");
                }

                $targets[] = $target;
            }

            $plans[] = [
                'type' => $plan['type'],
                'name' => $plan['name'],
                'options' => $plan['options'],
                'targets' => $targets,
            ];
        }

        return [
            'schema' => $fixture['schema'],
            'laravel_major' => $fixture['laravel_major'],
            'plans' => $plans,
        ];
    }
}

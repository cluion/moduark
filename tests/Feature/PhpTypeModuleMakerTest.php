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
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Tests\TestCase;

final class PhpTypeModuleMakerTest extends TestCase
{
    private string $temporaryBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryBasePath = sys_get_temp_dir().'/moduark-php-type-maker-'.bin2hex(random_bytes(8));

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

    public function test_php_type_plans_match_the_reviewed_laravel_major_fixture(): void
    {
        $major = (int) explode('.', Application::VERSION, 2)[0];
        $fixture = dirname(__DIR__).'/Fixtures/Generation/php-types-laravel-'.$major.'.json';
        $expected = $this->planFixture($fixture);

        self::assertSame(1, $expected['schema']);
        self::assertSame($major, $expected['laravel_major']);

        foreach ($expected['plans'] as $plan) {
            $command = sprintf(
                'moduark:make User %s %s %s --dry-run',
                $plan['type'],
                $plan['name'],
                $plan['options'],
            );

            $this->command($command)
                ->expectsOutputToContain('CREATE '.$plan['target'])
                ->assertSuccessful();
        }

        self::assertSame(
            [$this->temporaryBasePath.'/app/Modules/User/UserModule.php'],
            $this->files(),
        );
    }

    public function test_it_generates_nested_php_types_with_native_laravel_stubs(): void
    {
        $root = $this->temporaryBasePath.'/app/Modules/User';

        $this->command('moduark:make User class Support/InvokableTask --invokable')
            ->assertSuccessful();
        $this->command('moduark:make User cast Money/AmountCast --inbound')
            ->assertSuccessful();
        $this->command('moduark:make User enum Workflow/Status --string')
            ->assertSuccessful();
        $this->command('moduark:make User enum Workflow/Priority --int')
            ->assertSuccessful();
        $this->command('moduark:make User exception Billing/PaymentFailed --render --report')
            ->assertSuccessful();
        $this->command('moduark:make User interface Lookup/UserLookup')
            ->assertSuccessful();
        $this->command('moduark:make User scope Visibility/PublishedScope')
            ->assertSuccessful();
        $this->command('moduark:make User trait Serialization/SerializesAttributes')
            ->assertSuccessful();

        $class = (string) file_get_contents($root.'/Support/InvokableTask.php');
        $cast = (string) file_get_contents($root.'/Casts/Money/AmountCast.php');
        $stringEnum = (string) file_get_contents($root.'/Enums/Workflow/Status.php');
        $intEnum = (string) file_get_contents($root.'/Enums/Workflow/Priority.php');
        $exception = (string) file_get_contents(
            $root.'/Exceptions/Billing/PaymentFailed.php',
        );
        $interface = (string) file_get_contents($root.'/Contracts/Lookup/UserLookup.php');
        $scope = (string) file_get_contents(
            $root.'/Models/Scopes/Visibility/PublishedScope.php',
        );
        $trait = (string) file_get_contents(
            $root.'/Concerns/Serialization/SerializesAttributes.php',
        );

        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Support;',
            $class,
        );
        self::assertStringContainsString('public function __invoke()', $class);
        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Casts\\Money;',
            $cast,
        );
        self::assertStringContainsString('implements CastsInboundAttributes', $cast);
        self::assertStringContainsString('public function set(', $cast);
        self::assertStringNotContainsString('public function get(', $cast);
        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Enums\\Workflow;',
            $stringEnum,
        );
        self::assertStringContainsString('enum Status: string', $stringEnum);
        self::assertStringContainsString('enum Priority: int', $intEnum);
        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Exceptions\\Billing;',
            $exception,
        );
        self::assertStringContainsString('class PaymentFailed extends Exception', $exception);
        self::assertStringContainsString('public function report(): void', $exception);
        self::assertStringContainsString('public function render(Request $request): Response', $exception);
        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Contracts\\Lookup;',
            $interface,
        );
        self::assertStringContainsString('interface UserLookup', $interface);
        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Models\\Scopes\\Visibility;',
            $scope,
        );
        self::assertStringContainsString('class PublishedScope implements Scope', $scope);
        self::assertStringContainsString('public function apply(', $scope);
        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Concerns\\Serialization;',
            $trait,
        );
        self::assertStringContainsString('trait SerializesAttributes', $trait);
    }

    #[DataProvider('phpTypeCases')]
    public function test_php_types_share_collision_force_and_dry_run_behavior(
        string $type,
        string $name,
        string $relativePath,
        string $options,
    ): void {
        $path = $this->temporaryBasePath.'/app/Modules/User/'.$relativePath;
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

    #[DataProvider('unsupportedOptionCases')]
    public function test_php_types_reject_options_owned_by_other_descriptors(
        string $command,
        string $message,
    ): void {
        $this->command($command)
            ->expectsOutputToContain('Module Maker failed: '.$message)
            ->assertExitCode(2);
    }

    /** @return iterable<string, array{string, string, string, string}> */
    public static function phpTypeCases(): iterable
    {
        yield 'class' => ['class', 'Support/Task', 'Support/Task.php', '--invokable'];
        yield 'cast' => ['cast', 'Money/AmountCast', 'Casts/Money/AmountCast.php', '--inbound'];
        yield 'enum' => ['enum', 'Workflow/Status', 'Enums/Workflow/Status.php', '--string'];
        yield 'exception' => [
            'exception',
            'Billing/PaymentFailed',
            'Exceptions/Billing/PaymentFailed.php',
            '--render --report',
        ];
        yield 'interface' => [
            'interface',
            'Lookup/UserLookup',
            'Contracts/Lookup/UserLookup.php',
            '',
        ];
        yield 'scope' => [
            'scope',
            'Visibility/PublishedScope',
            'Models/Scopes/Visibility/PublishedScope.php',
            '',
        ];
        yield 'trait' => [
            'trait',
            'Serialization/SerializesAttributes',
            'Concerns/Serialization/SerializesAttributes.php',
            '',
        ];
    }

    /** @return iterable<string, array{string, string}> */
    public static function unsupportedOptionCases(): iterable
    {
        yield 'class factory' => [
            'moduark:make User class Support/Task --factory',
            'The --factory option is not supported for Maker type [class].',
        ];
        yield 'enum resource' => [
            'moduark:make User enum Workflow/Status --resource',
            'The --resource option is not supported for Maker type [enum].',
        ];
        yield 'interface invokable' => [
            'moduark:make User interface Lookup/UserLookup --invokable',
            'The --invokable option is not supported for Maker type [interface].',
        ];
        yield 'trait string' => [
            'moduark:make User trait Serialization/SerializesAttributes --string',
            'The --string option is not supported for Maker type [trait].',
        ];
        yield 'cast render' => [
            'moduark:make User cast Money/AmountCast --render',
            'The --render option is not supported for Maker type [cast].',
        ];
        yield 'exception inbound' => [
            'moduark:make User exception Billing/PaymentFailed --inbound',
            'The --inbound option is not supported for Maker type [exception].',
        ];
        yield 'scope report' => [
            'moduark:make User scope Visibility/PublishedScope --report',
            'The --report option is not supported for Maker type [scope].',
        ];
        yield 'model int' => [
            'moduark:make User model Profile --int',
            'The --int option is not supported for Maker type [model].',
        ];
        yield 'controller string' => [
            'moduark:make User controller ProfileController --string',
            'The --string option is not supported for Maker type [controller].',
        ];
        yield 'class report' => [
            'moduark:make User class Support/Task --report',
            'The --report option is not supported for Maker type [class].',
        ];
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
            throw new RuntimeException("PHP type plan fixture [{$path}] has an invalid root shape.");
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
                throw new RuntimeException("PHP type plan fixture [{$path}] has an invalid plan.");
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

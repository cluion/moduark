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

final class HttpModuleMakerTest extends TestCase
{
    private string $temporaryBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryBasePath = sys_get_temp_dir().'/moduark-http-maker-'.bin2hex(random_bytes(8));

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

    public function test_http_plans_match_the_reviewed_laravel_major_fixture(): void
    {
        $major = (int) explode('.', Application::VERSION, 2)[0];
        $fixture = dirname(__DIR__).'/Fixtures/Generation/http-types-laravel-'.$major.'.json';
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

    public function test_it_generates_nested_http_types_with_native_laravel_stubs(): void
    {
        $root = $this->temporaryBasePath.'/app/Modules/User/Http';

        $this->command('moduark:make User request Profile/StoreProfileRequest')
            ->assertSuccessful();
        $this->command('moduark:make User resource Profile/ProfileResource')
            ->assertSuccessful();
        $this->command('moduark:make User resource Profile/ProfileCollection --collection')
            ->assertSuccessful();
        $this->command('moduark:make User resource Profile/ProfileJsonApiResource --json-api')
            ->assertSuccessful();

        $request = (string) file_get_contents(
            $root.'/Requests/Profile/StoreProfileRequest.php',
        );
        $resource = (string) file_get_contents(
            $root.'/Resources/Profile/ProfileResource.php',
        );
        $collection = (string) file_get_contents(
            $root.'/Resources/Profile/ProfileCollection.php',
        );
        $jsonApi = (string) file_get_contents(
            $root.'/Resources/Profile/ProfileJsonApiResource.php',
        );

        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Http\\Requests\\Profile;',
            $request,
        );
        self::assertStringContainsString('class StoreProfileRequest extends FormRequest', $request);
        self::assertStringContainsString('public function authorize(): bool', $request);
        self::assertStringContainsString('public function rules(): array', $request);
        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Http\\Resources\\Profile;',
            $resource,
        );
        self::assertStringContainsString('class ProfileResource extends JsonResource', $resource);
        self::assertStringContainsString('class ProfileCollection extends ResourceCollection', $collection);
        self::assertStringContainsString(
            'class ProfileJsonApiResource extends JsonApiResource',
            $jsonApi,
        );
        self::assertStringContainsString('public $attributes = [', $jsonApi);
        self::assertStringContainsString('public $relationships = [', $jsonApi);
    }

    #[DataProvider('httpTypeCases')]
    public function test_http_types_share_collision_force_and_dry_run_behavior(
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

    #[DataProvider('invalidOptionCases')]
    public function test_http_types_reject_ambiguous_or_foreign_options(
        string $command,
        string $message,
    ): void {
        $this->command($command)
            ->expectsOutputToContain('Module Maker failed: '.$message)
            ->assertExitCode(2);
    }

    /** @return iterable<string, array{string, string, string, string}> */
    public static function httpTypeCases(): iterable
    {
        yield 'request' => [
            'request',
            'Profile/StoreProfileRequest',
            'Http/Requests/Profile/StoreProfileRequest.php',
            '',
        ];
        yield 'resource collection' => [
            'resource',
            'Profile/ProfileCollection',
            'Http/Resources/Profile/ProfileCollection.php',
            '--collection',
        ];
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidOptionCases(): iterable
    {
        yield 'request collection' => [
            'moduark:make User request Profile/StoreProfileRequest --collection',
            'The --collection option is not supported for Maker type [request].',
        ];
        yield 'resource inbound' => [
            'moduark:make User resource Profile/ProfileResource --inbound',
            'The --inbound option is not supported for Maker type [resource].',
        ];
        yield 'resource competing modes' => [
            'moduark:make User resource Profile/ProfileResource --collection --json-api',
            'The resource Maker options [--collection, --json-api] cannot be combined.',
        ];
        yield 'class json api' => [
            'moduark:make User class Support/Task --json-api',
            'The --json-api option is not supported for Maker type [class].',
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
            throw new RuntimeException("HTTP plan fixture [{$path}] has an invalid root shape.");
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
                throw new RuntimeException("HTTP plan fixture [{$path}] has an invalid plan.");
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

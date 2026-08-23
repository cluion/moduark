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

final class PolicyModuleMakerTest extends TestCase
{
    private string $temporaryBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryBasePath = sys_get_temp_dir().'/moduark-policy-maker-'.bin2hex(random_bytes(8));

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

    public function test_policy_plans_match_the_reviewed_laravel_major_fixture(): void
    {
        $major = (int) explode('.', Application::VERSION, 2)[0];
        $fixture = dirname(__DIR__).'/Fixtures/Generation/policy-laravel-'.$major.'.json';
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
    }

    public function test_it_generates_plain_and_module_model_policies_with_native_stubs(): void
    {
        config()->set('auth.guards.admin.provider', 'admins');
        config()->set('auth.providers.admins.model', 'MakerFixture\\Shared\\Admin');

        $this->command('moduark:make User policy Admin/ManageProfiles')
            ->assertSuccessful();
        $this->command(
            'moduark:make User policy Profile/ProfilePolicy --model=Profile --guard=admin',
        )->assertSuccessful();

        $plainPath = $this->temporaryBasePath.'/app/Modules/User/Policies/Admin/ManageProfiles.php';
        $modelPath = $this->temporaryBasePath.'/app/Modules/User/Policies/Profile/ProfilePolicy.php';
        $plain = (string) file_get_contents($plainPath);
        $model = (string) file_get_contents($modelPath);

        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Policies\\Admin;',
            $plain,
        );
        self::assertStringContainsString('class ManageProfiles', $plain);
        self::assertStringContainsString('public function __construct()', $plain);
        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Policies\\Profile;',
            $model,
        );
        self::assertStringContainsString(
            'use MakerFixture\\Modules\\User\\Models\\Profile;',
            $model,
        );
        self::assertStringContainsString('use MakerFixture\\Shared\\Admin;', $model);
        self::assertStringContainsString(
            'public function view(Admin $admin, Profile $profile): bool',
            $model,
        );
        self::assertStringContainsString(
            'public function forceDelete(Admin $admin, Profile $profile): bool',
            $model,
        );
        self::assertFileDoesNotExist(
            $this->temporaryBasePath.'/app/Modules/User/Models/Profile.php',
        );
        self::assertSame([
            $plainPath,
            $modelPath,
            $this->temporaryBasePath.'/app/Modules/User/UserModule.php',
        ], $this->files());
    }

    public function test_policy_shares_collision_force_and_dry_run_behavior(): void
    {
        $relativePath = 'Policies/Profile/ProfilePolicy.php';
        $path = $this->temporaryBasePath.'/app/Modules/User/'.$relativePath;
        $command = 'moduark:make User policy Profile/ProfilePolicy --model=Profile';

        $this->command($command.' --dry-run')
            ->expectsOutputToContain('CREATE '.$relativePath)
            ->assertSuccessful();
        self::assertFileDoesNotExist($path);

        $this->command($command)->assertSuccessful();
        self::assertIsInt(file_put_contents($path, 'existing source'));

        $this->command($command)
            ->expectsOutputToContain('Policy already exists.')
            ->assertFailed();
        self::assertSame('existing source', file_get_contents($path));

        $this->command($command.' --force')->assertSuccessful();
        self::assertNotSame('existing source', file_get_contents($path));
    }

    public function test_policy_rejects_foreign_or_invalid_model_options(): void
    {
        $this->command('moduark:make User policy ProfilePolicy --collection')
            ->expectsOutputToContain(
                'Module Maker failed: The --collection option is not supported for Maker type [policy].',
            )
            ->assertExitCode(2);
        $this->command('moduark:make User policy ProfilePolicy --model=profile')
            ->expectsOutputToContain(
                'Module Maker failed: Policy model [profile] must contain one or more StudlyCase class segments relative to the Module Models namespace.',
            )
            ->assertExitCode(2);
        $this->command('moduark:make User policy ProfilePolicy --model=../Profile')
            ->expectsOutputToContain(
                'must contain one or more StudlyCase class segments relative to the Module Models namespace.',
            )
            ->assertExitCode(2);
        $this->command('moduark:make User policy ProfilePolicy --model=/App/Models/Profile')
            ->expectsOutputToContain(
                'must contain one or more StudlyCase class segments relative to the Module Models namespace.',
            )
            ->assertExitCode(2);
        $this->command('moduark:make User policy ProfilePolicy --model=')
            ->expectsOutputToContain(
                'Module Maker failed: The --model option must be a non-empty string when provided.',
            )
            ->assertExitCode(2);

        self::assertSame(
            [$this->temporaryBasePath.'/app/Modules/User/UserModule.php'],
            $this->files(),
        );
    }

    public function test_invalid_guard_failure_leaves_no_policy_file(): void
    {
        $path = $this->temporaryBasePath.'/app/Modules/User/Policies/ProfilePolicy.php';

        $this->command('moduark:make User policy ProfilePolicy --model=Profile --guard=missing')
            ->expectsOutputToContain(
                'Module Maker failed: The [missing] guard is not defined in your "auth" configuration file.',
            )
            ->assertExitCode(2);

        self::assertFileDoesNotExist($path);
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
            throw new RuntimeException("Policy plan fixture [{$path}] has an invalid root shape.");
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
                throw new RuntimeException("Policy plan fixture [{$path}] has an invalid plan.");
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

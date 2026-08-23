<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Discovery\ModuleDiscoverer;
use Cluion\Moduark\Generation\GenerationExecutor;
use Cluion\Moduark\Module;
use Composer\Autoload\ClassLoader;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

final class MakeModuleCommandTest extends TestCase
{
    private string $temporaryBasePath;

    private string $modulePath;

    private string $namespace;

    private ClassLoader $loader;

    protected function setUp(): void
    {
        parent::setUp();

        $token = strtoupper(bin2hex(random_bytes(6)));
        $this->temporaryBasePath = sys_get_temp_dir().'/moduark-generator-'.$token;
        $applicationPath = $this->temporaryBasePath.'/app';
        $this->modulePath = $applicationPath.'/Modules';
        $this->namespace = 'Tests\\Generated\\T'.$token;

        self::assertTrue(mkdir($applicationPath, 0755, true));

        $this->loader = new ClassLoader($this->temporaryBasePath.'/vendor');
        $this->loader->addPsr4($this->namespace.'\\', $applicationPath);
        $this->loader->register(true);

        $this->useModulePath($this->modulePath);
    }

    protected function tearDown(): void
    {
        $this->loader->unregister();
        (new Filesystem)->deleteDirectory($this->temporaryBasePath);

        parent::tearDown();
    }

    public function test_it_creates_only_one_autoloadable_module_entry_class(): void
    {
        $path = $this->modulePath.'/Billing/BillingModule.php';
        $moduleClass = $this->namespace.'\\Modules\\Billing\\BillingModule';

        $this->command('moduark:make-module Billing')
            ->expectsOutputToContain("Module [{$path}] created successfully.")
            ->assertSuccessful();

        self::assertSame($this->expectedModuleSource(), file_get_contents($path));
        self::assertSame([$path], $this->generatedFiles());

        $registry = (new ModuleDiscoverer)->discover($this->modulePath);

        self::assertSame([$moduleClass], $registry->moduleClasses());
        self::assertTrue(is_subclass_of($moduleClass, Module::class));
    }

    public function test_explicit_minimal_and_dry_run_share_the_single_target_plan(): void
    {
        $path = $this->modulePath.'/Billing/BillingModule.php';

        $this->command('moduark:make-module Billing --preset=minimal --dry-run')
            ->expectsOutputToContain('Module scaffold plan [minimal] (dry run):')
            ->expectsOutputToContain('CREATE BillingModule.php')
            ->assertSuccessful();

        self::assertDirectoryDoesNotExist($this->modulePath);

        $this->command('moduark:make-module Billing --preset=minimal')
            ->expectsOutputToContain("Module [{$path}] created successfully.")
            ->assertSuccessful();

        self::assertSame([$path], $this->generatedFiles());
    }

    #[DataProvider('presetTargets')]
    public function test_it_executes_each_additive_preset(
        string $preset,
        int $targetCount,
        string $representativeTarget,
    ): void {
        $module = ucfirst($preset).'Module';

        $this->command("moduark:make-module {$module} --preset={$preset}")
            ->expectsOutputToContain(
                "Preset [{$preset}] created {$targetCount} Module-owned targets.",
            )
            ->assertSuccessful();

        self::assertFileExists($this->modulePath.'/'.$module.'/'.$representativeTarget);
        self::assertFileExists($this->modulePath.'/'.$module.'/'.$module.'Module.php');
    }

    public function test_full_dry_run_renders_the_fixture_without_mutation(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(
                dirname(__DIR__).'/Fixtures/Generation/scaffold-presets.json',
            ),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($fixture);
        self::assertIsArray($fixture['presets'] ?? null);
        self::assertIsArray($fixture['presets']['full'] ?? null);
        $pending = $this->command('moduark:make-module Blog --preset=full --dry-run');

        foreach ($fixture['presets']['full'] as $target) {
            self::assertIsArray($target);
            self::assertIsString($target['target'] ?? null);
            $pending->expectsOutputToContain('CREATE '.$target['target']);
        }

        $pending->assertSuccessful();
        self::assertDirectoryDoesNotExist($this->modulePath);
    }

    public function test_full_json_dry_run_matches_the_permanent_plan_output_fixture(): void
    {
        [$exitCode, $payload, $json] = $this->jsonPlan([
            'name' => 'Blog',
            '--preset' => 'full',
            '--dry-run' => true,
            '--format' => 'json',
        ]);

        self::assertSame(0, $exitCode);
        self::assertSame($this->planOutputFixture('scaffold'), $payload);
        self::assertStringNotContainsString("\e", $json);
        self::assertDirectoryDoesNotExist($this->modulePath);
    }

    public function test_scaffold_json_reports_collisions_without_partial_files(): void
    {
        $collision = $this->modulePath.'/Blog/routes/api.php';
        self::assertTrue(mkdir(dirname($collision), 0755, true));
        self::assertIsInt(file_put_contents($collision, 'existing route'));

        [$exitCode, $payload] = $this->jsonPlan([
            'name' => 'Blog',
            '--preset' => 'full',
            '--dry-run' => true,
            '--format' => 'json',
        ]);

        self::assertSame(1, $exitCode);
        self::assertSame('collisions_found', $payload['status'] ?? null);
        $summary = $payload['summary'] ?? null;
        self::assertIsArray($summary);
        self::assertSame(1, $summary['collisions'] ?? null);
        self::assertSame(14, $summary['targets'] ?? null);
        self::assertSame('existing route', file_get_contents($collision));
        self::assertSame([$collision], $this->generatedFilesRecursively());
    }

    public function test_scaffold_json_requires_dry_run_and_reports_invalid_presets(): void
    {
        [$formatExit, $formatFailure] = $this->jsonPlan([
            'name' => 'Blog',
            '--preset' => 'full',
            '--format' => 'json',
        ]);
        self::assertSame(ExitPolicy::TOOL_ERROR, $formatExit);
        $formatError = $formatFailure['error'] ?? null;
        self::assertIsArray($formatError);
        self::assertSame('MOD-SCAFFOLD-OPTION-001', $formatError['code'] ?? null);

        [$presetExit, $presetFailure] = $this->jsonPlan([
            'name' => 'Blog',
            '--preset' => 'frontend',
            '--dry-run' => true,
            '--format' => 'json',
        ]);
        self::assertSame(1, $presetExit);
        self::assertSame('incomplete', $presetFailure['status'] ?? null);
        $presetError = $presetFailure['error'] ?? null;
        self::assertIsArray($presetError);
        self::assertSame('MOD-SCAFFOLD-PLAN-001', $presetError['code'] ?? null);
        self::assertDirectoryDoesNotExist($this->modulePath);
    }

    public function test_scaffold_rejects_an_unknown_plan_output_format(): void
    {
        $this->command('moduark:make-module Blog --dry-run --format=yaml')
            ->expectsOutputToContain('The --format option must be text or json.')
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    public function test_preset_collision_preflight_leaves_no_partial_module(): void
    {
        $collision = $this->modulePath.'/Blog/routes/api.php';
        self::assertTrue(mkdir(dirname($collision), 0755, true));
        self::assertIsInt(file_put_contents($collision, 'existing route'));

        $this->command('moduark:make-module Blog --preset=full')
            ->expectsOutputToContain('Module scaffold target [routes/api.php] already exists.')
            ->assertFailed();

        self::assertSame('existing route', file_get_contents($collision));
        self::assertSame([$collision], $this->generatedFilesRecursively());
    }

    public function test_preset_write_failure_rolls_back_every_target(): void
    {
        $filesystem = new class extends Filesystem
        {
            public function replace($path, $content, $mode = null): void
            {
                if (str_contains((string) $path, '/Http/Requests/Api/')) {
                    throw new RuntimeException('Injected preset write failure.');
                }

                parent::replace($path, $content, $mode);
            }
        };
        $this->application()->instance(
            GenerationExecutor::class,
            new GenerationExecutor($filesystem),
        );

        $this->command('moduark:make-module Blog --preset=full')
            ->expectsOutputToContain('Module generation failed: Injected preset write failure.')
            ->expectsOutputToContain(
                'Module scaffold failed; all planned filesystem changes were rolled back.',
            )
            ->assertExitCode(ExitPolicy::TOOL_ERROR);

        self::assertDirectoryDoesNotExist($this->modulePath);
    }

    public function test_it_rejects_an_unknown_preset_without_mutation(): void
    {
        $this->command('moduark:make-module Blog --preset=frontend')
            ->expectsOutputToContain(
                'Module scaffold preset [frontend] is not supported; expected minimal, web, api, domain, or full.',
            )
            ->assertFailed();

        self::assertDirectoryDoesNotExist($this->modulePath);
    }

    /**
     * @param non-empty-string $name
     */
    #[DataProvider('invalidNames')]
    public function test_it_rejects_non_studly_or_unsafe_names(string $name): void
    {
        $this->command('moduark:make-module '.$name)
            ->expectsOutputToContain("Module name [{$name}] must be StudlyCase")
            ->assertFailed();

        self::assertDirectoryDoesNotExist($this->modulePath);
    }

    public function test_it_rejects_php_reserved_names(): void
    {
        $this->command('moduark:make-module Class')
            ->expectsOutputToContain('Module name [Class] is reserved by PHP.')
            ->assertFailed();

        self::assertDirectoryDoesNotExist($this->modulePath);
    }

    public function test_it_never_overwrites_an_existing_entry_file(): void
    {
        $path = $this->modulePath.'/User/UserModule.php';

        $this->command('moduark:make-module User')->assertSuccessful();
        self::assertIsInt(file_put_contents($path, 'existing source'));

        $this->command('moduark:make-module User')
            ->expectsOutputToContain("Module entry file [{$path}] already exists.")
            ->assertFailed();

        self::assertSame('existing source', file_get_contents($path));
    }

    public function test_it_rejects_a_module_path_outside_composer_psr4_mappings(): void
    {
        $unmappedPath = $this->temporaryBasePath.'/unmapped/Modules';
        $this->useModulePath($unmappedPath);

        $this->command('moduark:make-module User')
            ->expectsOutputToContain(
                "Module path [{$unmappedPath}] is not inside a registered Composer PSR-4 path.",
            )
            ->assertFailed();

        self::assertDirectoryDoesNotExist($unmappedPath);
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'camel case' => ['user'];
        yield 'path separator' => ['User/Profile'];
        yield 'parent traversal' => ['../User'];
        yield 'underscore' => ['User_Profile'];
        yield 'leading number' => ['1User'];
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function presetTargets(): iterable
    {
        yield 'web' => ['web', 6, 'resources/views/index.blade.php'];
        yield 'api' => ['api', 6, 'Http/Resources/Api/ApiModuleResource.php'];
        yield 'domain' => ['domain', 4, 'Domain/.gitkeep'];
        yield 'full' => ['full', 14, 'Tests/Feature/Api/FullModuleApiTest.php'];
    }

    private function useModulePath(string $path): void
    {
        $defaults = require dirname(__DIR__, 2).'/config/moduark.php';

        self::assertIsArray($defaults);

        $this->application()->instance(
            ModulesConfig::class,
            ModulesConfig::from($defaults, ['path' => $path]),
        );
    }

    /**
     * @param array<string, bool|string> $parameters
     * @return array{int, array<mixed>, string}
     */
    private function jsonPlan(array $parameters): array
    {
        $output = new BufferedOutput;
        $exitCode = $this->application()->make(Kernel::class)->call(
            'moduark:make-module',
            $parameters,
            $output,
        );
        $json = trim($output->fetch());
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);
        self::assertStringNotContainsString($this->temporaryBasePath, $json);

        return [$exitCode, $payload, $json];
    }

    /** @return array<mixed> */
    private function planOutputFixture(string $key): array
    {
        $fixture = json_decode(
            (string) file_get_contents(
                dirname(__DIR__).'/Fixtures/Generation/plan-output.json',
            ),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($fixture);
        self::assertIsArray($fixture[$key] ?? null);

        return $fixture[$key];
    }

    private function expectedModuleSource(): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespace}\Modules\Billing;

use Cluion\Moduark\Module;

final class BillingModule extends Module
{
}
PHP."\n";
    }

    /**
     * @return list<string>
     */
    private function generatedFiles(): array
    {
        $matches = glob($this->modulePath.'/*/*');

        if ($matches === false) {
            self::fail('Unable to inspect generated Module files.');
        }

        $files = [];

        foreach ($matches as $path) {
            if (is_file($path)) {
                $files[] = $path;
            }
        }

        sort($files, SORT_STRING);

        return $files;
    }

    /** @return list<string> */
    private function generatedFilesRecursively(): array
    {
        if (! is_dir($this->modulePath)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->modulePath),
        );

        foreach ($iterator as $file) {
            if (! $file instanceof \SplFileInfo) {
                continue;
            }

            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        sort($files, SORT_STRING);

        return $files;
    }
}

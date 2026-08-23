<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Exceptions\ModuleGenerationFailed;
use Cluion\Moduark\Generation\GenerationPreflight;
use Cluion\Moduark\Generation\GenerationTarget;
use Cluion\Moduark\Generation\ModuleScaffoldPlanner;
use Cluion\Moduark\Generation\ModuleScaffoldPreset;
use Composer\Autoload\ClassLoader;
use Illuminate\Filesystem\Filesystem;
use ParseError;
use RuntimeException;
use Tests\TestCase;

final class ModuleScaffoldPlannerTest extends TestCase
{
    private string $temporaryBasePath;

    private string $modulePath;

    private string $namespace;

    private ClassLoader $loader;

    protected function setUp(): void
    {
        parent::setUp();

        $token = strtoupper(bin2hex(random_bytes(6)));
        $this->temporaryBasePath = sys_get_temp_dir().'/moduark-scaffold-plan-'.$token;
        $applicationPath = $this->temporaryBasePath.'/app';
        $this->modulePath = $applicationPath.'/Modules';
        $this->namespace = 'Tests\\Scaffold\\T'.$token;

        self::assertTrue(mkdir($applicationPath, 0755, true));

        $this->loader = new ClassLoader($this->temporaryBasePath.'/vendor');
        $this->loader->addPsr4($this->namespace.'\\', $applicationPath);
        $this->loader->register(true);

        $defaults = require dirname(__DIR__, 2).'/config/moduark.php';
        self::assertIsArray($defaults);
        $this->application()->instance(
            ModulesConfig::class,
            ModulesConfig::from($defaults, ['path' => $this->modulePath]),
        );
    }

    protected function tearDown(): void
    {
        $this->loader->unregister();
        (new Filesystem)->deleteDirectory($this->temporaryBasePath);

        parent::tearDown();
    }

    public function test_plans_match_the_permanent_preset_fixture_without_mutation(): void
    {
        $fixture = $this->fixture();
        self::assertSame(1, $fixture['schema']);
        self::assertSame('Blog', $fixture['module']);

        foreach ($fixture['presets'] as $preset => $expected) {
            $targets = $this->planner()->plan(
                $fixture['module'],
                ModuleScaffoldPreset::parse($preset),
            )->targets();

            self::assertSame($expected, array_map(
                static fn (GenerationTarget $target): array => [
                    'generator' => $target->generatorId(),
                    'target' => $target->moduleRelativePath(),
                ],
                $targets,
            ));
            self::assertCount(count($targets), array_unique(array_map(
                static fn (GenerationTarget $target): string => $target->filePath(),
                $targets,
            )));

            foreach ($targets as $target) {
                self::assertStringStartsWith($this->modulePath.'/Blog/', $target->filePath());
                $template = $target->template();
                self::assertNotNull($template);
                $rendered = $template->render(new Filesystem);

                if (str_ends_with($target->moduleRelativePath(), '.php')) {
                    $this->assertValidPhp($rendered);
                }
            }
        }

        self::assertDirectoryDoesNotExist($this->modulePath);
    }

    public function test_presets_are_additive_and_full_is_the_deterministic_union(): void
    {
        $plans = [];

        foreach (ModuleScaffoldPreset::cases() as $preset) {
            $plans[$preset->value] = array_map(
                static fn (GenerationTarget $target): string => $target->moduleRelativePath(),
                $this->planner()->plan('Blog', $preset)->targets(),
            );
        }

        foreach (['web', 'api', 'domain', 'full'] as $preset) {
            self::assertSame('BlogModule.php', $plans[$preset][0]);
            self::assertSame([], array_diff($plans['minimal'], $plans[$preset]));
        }

        self::assertSame(
            $plans['full'],
            array_values(array_unique([
                ...$plans['web'],
                ...$plans['api'],
                ...$plans['domain'],
            ])),
        );
    }

    public function test_preflight_reports_every_preset_owned_collision(): void
    {
        $plan = $this->planner()->plan('Blog', ModuleScaffoldPreset::Full);

        foreach (['BlogModule.php', 'routes/web.php', 'Domain/.gitkeep'] as $relativePath) {
            $path = $this->modulePath.'/Blog/'.$relativePath;
            (new Filesystem)->ensureDirectoryExists(dirname($path));
            self::assertIsInt(file_put_contents($path, 'existing'));
        }

        $collisions = (new GenerationPreflight)->collisions($plan);
        $ids = array_map(
            static fn (GenerationTarget $target): string => $target->generatorId(),
            $collisions,
        );
        sort($ids, SORT_STRING);

        self::assertSame(['domain-directory', 'module', 'web-route'], $ids);
    }

    public function test_it_rejects_an_unknown_preset(): void
    {
        $this->expectException(ModuleGenerationFailed::class);
        $this->expectExceptionMessage(
            'Module scaffold preset [frontend] is not supported; expected minimal, web, api, domain, or full.',
        );

        ModuleScaffoldPreset::parse('frontend');
    }

    private function planner(): ModuleScaffoldPlanner
    {
        return $this->application()->make(ModuleScaffoldPlanner::class);
    }

    /** @return array{schema: int, module: non-empty-string, presets: array<string, list<array{generator: string, target: string}>>} */
    private function fixture(): array
    {
        $path = dirname(__DIR__).'/Fixtures/Generation/scaffold-presets.json';
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read scaffold preset fixture [{$path}].");
        }

        /** @var array{schema: int, module: non-empty-string, presets: array<string, list<array{generator: string, target: string}>>} $fixture */
        $fixture = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $fixture;
    }

    private function assertValidPhp(string $contents): void
    {
        try {
            self::assertNotEmpty(token_get_all($contents, TOKEN_PARSE));
        } catch (ParseError $error) {
            self::fail($error->getMessage());
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Testing\PendingCommand;
use LogicException;
use Tests\TestCase;

final class LaravelMakerFeasibilityTest extends TestCase
{
    private string $temporaryBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryBasePath = sys_get_temp_dir().'/moduark-maker-'.bin2hex(random_bytes(8));

        self::assertTrue(mkdir($this->temporaryBasePath.'/app', 0755, true));
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

        $this->application()->setBasePath($this->temporaryBasePath);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->temporaryBasePath);

        parent::tearDown();
    }

    public function test_native_makers_accept_module_qualified_names_and_keep_native_options(): void
    {
        $rootNamespace = rtrim($this->application()->getNamespace(), '\\');
        $model = $rootNamespace.'\\Modules\\User\\Models\\Profile';
        $controller = $rootNamespace.'\\Modules\\User\\Http\\Controllers\\ProfileController';

        $this->maker('make:model', ['name' => $model])
            ->assertSuccessful();
        $this->maker('make:controller', [
            'name' => $controller,
            '--invokable' => true,
        ])->assertSuccessful();

        $modelPath = $this->temporaryBasePath.'/app/Modules/User/Models/Profile.php';
        $controllerPath = $this->temporaryBasePath.'/app/Modules/User/Http/Controllers/ProfileController.php';

        self::assertFileExists($modelPath);
        self::assertFileExists($controllerPath);
        self::assertStringContainsString(
            "namespace {$rootNamespace}\\Modules\\User\\Models;",
            (string) file_get_contents($modelPath),
        );
        self::assertStringContainsString(
            "namespace {$rootNamespace}\\Modules\\User\\Http\\Controllers;",
            (string) file_get_contents($controllerPath),
        );
        self::assertStringContainsString(
            'public function __invoke(',
            (string) file_get_contents($controllerPath),
        );
    }

    public function test_model_related_generation_does_not_propagate_module_context(): void
    {
        $rootNamespace = rtrim($this->application()->getNamespace(), '\\');
        $model = $rootNamespace.'\\Modules\\User\\Models\\Profile';

        $this->maker('make:model', [
            'name' => $model,
            '--controller' => true,
        ])->assertSuccessful();

        self::assertFileExists($this->temporaryBasePath.'/app/Modules/User/Models/Profile.php');
        self::assertFileExists($this->temporaryBasePath.'/app/Http/Controllers/ProfileController.php');
        self::assertFileDoesNotExist(
            $this->temporaryBasePath.'/app/Modules/User/Http/Controllers/ProfileController.php',
        );
    }

    /**
     * @param array<string, bool|string> $parameters
     */
    private function maker(string $command, array $parameters): PendingCommand
    {
        $pending = $this->artisan($command, $parameters);

        if (! $pending instanceof PendingCommand) {
            throw new LogicException("The [{$command}] command did not return a pending test command.");
        }

        return $pending;
    }
}

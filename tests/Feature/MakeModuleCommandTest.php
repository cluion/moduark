<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Discovery\ModuleDiscoverer;
use Cluion\Moduark\Module;
use Composer\Autoload\ClassLoader;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\DataProvider;
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

        $this->command('make:module Billing')
            ->expectsOutputToContain("Module [{$path}] created successfully.")
            ->assertSuccessful();

        self::assertSame($this->expectedModuleSource(), file_get_contents($path));
        self::assertSame([$path], $this->generatedFiles());

        $registry = (new ModuleDiscoverer)->discover($this->modulePath);

        self::assertSame([$moduleClass], $registry->moduleClasses());
        self::assertTrue(is_subclass_of($moduleClass, Module::class));
    }

    /**
     * @param non-empty-string $name
     */
    #[DataProvider('invalidNames')]
    public function test_it_rejects_non_studly_or_unsafe_names(string $name): void
    {
        $this->command('make:module '.$name)
            ->expectsOutputToContain("Module name [{$name}] must be StudlyCase")
            ->assertFailed();

        self::assertDirectoryDoesNotExist($this->modulePath);
    }

    public function test_it_rejects_php_reserved_names(): void
    {
        $this->command('make:module Class')
            ->expectsOutputToContain('Module name [Class] is reserved by PHP.')
            ->assertFailed();

        self::assertDirectoryDoesNotExist($this->modulePath);
    }

    public function test_it_never_overwrites_an_existing_entry_file(): void
    {
        $path = $this->modulePath.'/User/UserModule.php';

        $this->command('make:module User')->assertSuccessful();
        self::assertIsInt(file_put_contents($path, 'existing source'));

        $this->command('make:module User')
            ->expectsOutputToContain("Module entry file [{$path}] already exists.")
            ->assertFailed();

        self::assertSame('existing source', file_get_contents($path));
    }

    public function test_it_rejects_a_module_path_outside_composer_psr4_mappings(): void
    {
        $unmappedPath = $this->temporaryBasePath.'/unmapped/Modules';
        $this->useModulePath($unmappedPath);

        $this->command('make:module User')
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

    private function useModulePath(string $path): void
    {
        $defaults = require dirname(__DIR__, 2).'/config/modules.php';

        self::assertIsArray($defaults);

        $this->application()->instance(
            ModulesConfig::class,
            ModulesConfig::from($defaults, ['path' => $path]),
        );
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
}

<?php

declare(strict_types=1);

namespace Tests\Interop;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class NwidartApplicationRunner
{
    private const MODULE_DIRECTORY = 'InteropModules';

    private string $packagePath;

    public function __construct(
        string $packagePath,
        private readonly ?string $packageVersion = null,
        private readonly bool $keep = false,
    ) {
        $resolved = realpath($packagePath);

        if ($resolved === false || ! is_file($resolved.'/composer.json')) {
            throw new RuntimeException("Moduark package path [{$packagePath}] is invalid.");
        }

        $this->packagePath = $resolved;
    }

    public static function parsePackageVersion(mixed $value): ?string
    {
        if ($value === false) {
            return null;
        }

        if (
            ! is_string($value)
            || preg_match(
                '/\A(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?\z/',
                $value,
            ) !== 1
        ) {
            throw new RuntimeException(
                'The --package option must be an exact stable or pre-release version.',
            );
        }

        return $value;
    }

    public function run(): string
    {
        $root = sys_get_temp_dir().'/moduark-nwidart-interop-'.bin2hex(random_bytes(8));
        $application = $root.'/laravel-13';

        if (! mkdir($root, 0755, true)) {
            throw new RuntimeException("Unable to create interoperability root [{$root}].");
        }

        $environment = getenv();
        $environment['APP_ENV'] = 'testing';
        $environment['CACHE_STORE'] = 'array';
        $environment['COMPOSER_HOME'] = $root.'/composer-home';
        $environment['COMPOSER_CACHE_DIR'] = $root.'/composer-cache';
        $environment['COMPOSER_NO_INTERACTION'] = '1';
        $environment['COMPOSER_PROCESS_TIMEOUT'] = '900';
        $environment['DB_CONNECTION'] = 'sqlite';
        $environment['DB_DATABASE'] = ':memory:';
        $environment['QUEUE_CONNECTION'] = 'sync';
        $environment['SESSION_DRIVER'] = 'array';

        echo "Nwidart interoperability root: {$root}\n";
        echo $this->packageVersion === null
            ? "Package source: current checkout as cluion/moduark:dev-main\n"
            : "Package source: Packagist cluion/moduark:{$this->packageVersion}\n";

        try {
            $this->command([
                'composer',
                'create-project',
                'laravel/laravel:^13.0',
                $application,
                '--no-interaction',
                '--no-progress',
                '--prefer-dist',
            ], $root, $environment);

            $this->installPackages($application, $environment);
            $this->publishAndVerifyConfigIsolation($application, $environment);
            $this->createNwidartModules($application, $environment);
            $this->installInteropSources($application);
            $this->command(['composer', 'dump-autoload', '--no-interaction'], $application, $environment);
            $this->installProbeCommand($application);
            $this->verifyCommandOwnership($application, $environment);
            $this->verifyEffectiveConfiguration($application, $environment);
            $this->verifyDiscoveryAndAnalysis($application, $environment);
            $this->verifyCacheAndRoutes($application, $environment);

            $versionOutput = $this->artisan($application, ['--version'], $environment);

            if (preg_match('/Laravel Framework ([^\s]+)/', $versionOutput, $match) !== 1) {
                throw new RuntimeException('Unable to determine the installed Laravel framework version.');
            }

            return $match[1];
        } finally {
            if ($this->keep) {
                echo "Preserved interoperability root: {$root}\n";
            } else {
                $this->deleteDirectory($root);
                echo "Removed interoperability root: {$root}\n";
            }
        }
    }

    /** @param array<string, string> $environment */
    private function installPackages(string $application, array $environment): void
    {
        $constraint = $this->packageVersion ?? 'dev-main';

        $this->command([
            'composer',
            'config',
            '--no-plugins',
            'allow-plugins.wikimedia/composer-merge-plugin',
            'true',
        ], $application, $environment);

        if ($this->packageVersion === null) {
            $repository = json_encode([
                'type' => 'path',
                'url' => $this->packagePath,
                'options' => [
                    'versions' => ['cluion/moduark' => 'dev-main'],
                ],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

            $this->command([
                'composer',
                'config',
                '--json',
                'repositories.moduark',
                $repository,
            ], $application, $environment);
        }

        $this->command([
            'composer',
            'require',
            'nwidart/laravel-modules:^13.0',
            'cluion/moduark:'.$constraint,
            '--no-interaction',
            '--no-progress',
            '--prefer-dist',
        ], $application, $environment);
    }

    /** @param array<string, string> $environment */
    private function publishAndVerifyConfigIsolation(string $application, array $environment): void
    {
        $this->artisan($application, [
            'vendor:publish',
            '--provider=Nwidart\\Modules\\LaravelModulesServiceProvider',
            '--tag=config',
            '--force',
        ], $environment);
        $nwidartConfig = $application.'/config/modules.php';
        $this->assertFileExists($nwidartConfig, 'nwidart did not publish config/modules.php.');
        $configuration = $this->contents($nwidartConfig);
        $updatedConfiguration = str_replace(
            ["base_path('Modules')", 'base_path("Modules")'],
            ["base_path('".self::MODULE_DIRECTORY."')", 'base_path("'.self::MODULE_DIRECTORY.'")'],
            $configuration,
            $replacementCount,
        );

        if ($replacementCount !== 1) {
            throw new RuntimeException('Unable to configure a non-default nwidart Module root.');
        }

        if (file_put_contents($nwidartConfig, $updatedConfiguration) === false) {
            throw new RuntimeException('Unable to update the nwidart Module root.');
        }

        $before = hash_file('sha256', $nwidartConfig);

        $this->artisan($application, [
            'vendor:publish',
            '--provider=Cluion\\Moduark\\ModuarkServiceProvider',
            '--tag=moduark-config',
            '--force',
        ], $environment);

        $this->assertFileExists(
            $application.'/config/moduark.php',
            'Moduark did not publish config/moduark.php.',
        );
        $after = hash_file('sha256', $nwidartConfig);

        if (! is_string($before) || $before !== $after) {
            throw new RuntimeException('Publishing Moduark changed nwidart config/modules.php.');
        }
    }

    /** @param array<string, string> $environment */
    private function createNwidartModules(string $application, array $environment): void
    {
        $this->artisan(
            $application,
            ['module:make', 'User', 'Order', '--no-interaction'],
            $environment,
        );

        foreach (['User', 'Order'] as $module) {
            $this->assertFileExists(
                $application.'/'.self::MODULE_DIRECTORY.'/'.$module.'/module.json',
                "nwidart module:make did not create [{$module}].",
            );
        }

        $composerPath = $application.'/composer.json';
        $composer = json_decode($this->contents($composerPath), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($composer)) {
            throw new RuntimeException('The generated Laravel composer.json is invalid.');
        }

        $autoload = $composer['autoload'] ?? null;

        if (! is_array($autoload)) {
            throw new RuntimeException('The generated Laravel composer.json has no autoload object.');
        }

        $psr4 = $autoload['psr-4'] ?? null;

        if (! is_array($psr4)) {
            throw new RuntimeException('The generated Laravel composer.json has no PSR-4 map.');
        }

        $psr4['Modules\\User\\'] = self::MODULE_DIRECTORY.'/User/app/';
        $psr4['Modules\\Order\\'] = self::MODULE_DIRECTORY.'/Order/app/';
        $autoload['psr-4'] = $psr4;
        $composer['autoload'] = $autoload;
        $encoded = json_encode(
            $composer,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ).PHP_EOL;

        if (file_put_contents($composerPath, $encoded) === false) {
            throw new RuntimeException('Unable to add nwidart Module PSR-4 mappings.');
        }
    }

    private function installInteropSources(string $application): void
    {
        $sources = [
            self::MODULE_DIRECTORY.'/User/app/UserModule.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace Modules\User;

use Cluion\Moduark\Module;

final class UserModule extends Module
{
}
PHP,
            self::MODULE_DIRECTORY.'/User/app/Contracts/UserDirectory.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace Modules\User\Contracts;

interface UserDirectory
{
}
PHP,
            self::MODULE_DIRECTORY.'/User/app/Events/UserCreated.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace Modules\User\Events;

final class UserCreated
{
}
PHP,
            self::MODULE_DIRECTORY.'/User/app/Services/InternalUserService.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace Modules\User\Services;

final class InternalUserService
{
}
PHP,
            self::MODULE_DIRECTORY.'/Order/app/OrderModule.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace Modules\Order;

use Cluion\Moduark\Module;
use Modules\User\UserModule;

final class OrderModule extends Module
{
    public function dependencies(): array
    {
        return [UserModule::class];
    }
}
PHP,
            self::MODULE_DIRECTORY.'/Order/app/Actions/ObserveUser.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace Modules\Order\Actions;

use Modules\User\Contracts\UserDirectory;
use Modules\User\Events\UserCreated;
use Modules\User\Services\InternalUserService;

final class ObserveUser
{
    public function __invoke(
        UserDirectory $directory,
        UserCreated $event,
        InternalUserService $service,
    ): void {
    }
}
PHP,
        ];

        foreach ($sources as $relativePath => $source) {
            $path = $application.'/'.$relativePath;
            $directory = dirname($path);

            if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new RuntimeException("Unable to create interoperability directory [{$directory}].");
            }

            if (file_put_contents($path, $source.PHP_EOL) === false) {
                throw new RuntimeException("Unable to write interoperability source [{$path}].");
            }
        }
    }

    private function installProbeCommand(string $application): void
    {
        $source = <<<'PHP'
<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('__moduark:interop-probe', function (): void {
    $this->line(json_encode([
        'moduark_path' => config('moduark.path'),
        'nwidart_path' => config('modules.paths.modules'),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
});
PHP;

        if (file_put_contents($application.'/routes/console.php', $source.PHP_EOL) === false) {
            throw new RuntimeException('Unable to install the interoperability probe command.');
        }
    }

    /** @param array<string, string> $environment */
    private function verifyCommandOwnership(string $application, array $environment): void
    {
        $nwidart = $this->artisan($application, ['help', 'module:make'], $environment);
        $moduark = $this->artisan($application, ['help', 'moduark:make'], $environment);

        $this->assertContains('Create a new module.', $nwidart, 'nwidart module:make was overwritten.');
        $this->assertContains('[<name>...]', $nwidart, 'nwidart module:make lost its name list argument.');
        $this->assertContains(
            'Generate a model or controller inside an existing Module',
            $moduark,
            'Moduark resource maker did not keep its independent command contract.',
        );
        $this->assertContains(
            '<module> <type> <name>',
            $moduark,
            'Moduark resource maker lost its required arguments.',
        );
    }

    /** @param array<string, string> $environment */
    private function verifyEffectiveConfiguration(string $application, array $environment): void
    {
        $output = $this->artisan($application, ['__moduark:interop-probe'], $environment);
        $payload = json_decode(trim($output), true, 512, JSON_THROW_ON_ERROR);
        $expected = realpath($application.'/'.self::MODULE_DIRECTORY);

        if (! is_array($payload)) {
            throw new RuntimeException('The interoperability config probe did not return an object.');
        }

        $moduarkPath = $payload['moduark_path'] ?? null;
        $nwidartPath = $payload['nwidart_path'] ?? null;

        if (
            ! is_string($expected)
            || ! is_string($moduarkPath)
            || ! is_string($nwidartPath)
            || realpath($moduarkPath) !== $expected
            || realpath($nwidartPath) !== $expected
        ) {
            throw new RuntimeException('Moduark and nwidart did not resolve the same Modules root.');
        }
    }

    /** @param array<string, string> $environment */
    private function verifyDiscoveryAndAnalysis(string $application, array $environment): void
    {
        $nwidartList = $this->artisan($application, ['module:list'], $environment);
        $moduarkList = $this->artisan($application, ['moduark:list'], $environment);

        foreach (['Order', 'User'] as $module) {
            $this->assertContains($module, $nwidartList, "nwidart did not list [{$module}].");
            $this->assertContains($module, $moduarkList, "Moduark did not discover [{$module}].");
        }

        $levelZero = $this->artisan($application, ['moduark:check', '--level=0'], $environment);
        $this->assertContains(
            'Architecture check passed: 2 rules evaluated at Level 0 (Organization).',
            $levelZero,
            'The nwidart fixture did not pass Level 0.',
        );

        $result = $this->artisanResult(
            $application,
            ['moduark:check', '--level=1', '--format=json'],
            $environment,
            [1],
        );
        $payload = json_decode(trim($result['output']), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            throw new RuntimeException('The nwidart Level 1 check did not return an object.');
        }

        $summary = $payload['summary'] ?? null;

        if (
            ($payload['status'] ?? null) !== 'violations_found'
            || ($payload['complete'] ?? null) !== true
            || ! is_array($summary)
            || ($summary['errors'] ?? null) !== 1
        ) {
            throw new RuntimeException('The nwidart Level 1 precision result was not exactly one reviewed error.');
        }

        $violations = [];

        $results = $payload['results'] ?? null;

        if (! is_array($results)) {
            throw new RuntimeException('The nwidart Level 1 check omitted rule results.');
        }

        foreach ($results as $rule) {
            if (! is_array($rule)) {
                throw new RuntimeException('The nwidart Level 1 check returned an invalid rule result.');
            }

            $ruleViolations = $rule['violations'] ?? null;

            if (! is_array($ruleViolations)) {
                throw new RuntimeException('The nwidart Level 1 rule result omitted violations.');
            }

            foreach ($ruleViolations as $violation) {
                if (! is_array($violation)) {
                    throw new RuntimeException('The nwidart Level 1 check returned an invalid violation.');
                }

                $violations[] = $violation;
            }
        }

        $violation = $violations[0] ?? null;

        if (
            count($violations) !== 1
            || ! is_array($violation)
            || ($violation['rule'] ?? null) !== 'internal_api_access'
            || ($violation['symbol'] ?? null) !== 'Modules\\User\\Services\\InternalUserService'
        ) {
            throw new RuntimeException(
                'Contracts or Events were treated as internal, or internal Services were broadened to public.',
            );
        }
    }

    /** @param array<string, string> $environment */
    private function verifyCacheAndRoutes(string $application, array $environment): void
    {
        $cache = $this->artisan($application, ['moduark:cache'], $environment);
        $this->assertContains('2 Modules cached', $cache, 'The nwidart Module registry was not cached.');

        $before = $this->routeInventory($application, $environment);
        $this->artisan($application, ['optimize'], $environment);
        $this->artisan($application, ['moduark:list'], $environment);
        $after = $this->routeInventory($application, $environment);

        if ($before !== $after) {
            throw new RuntimeException('Route inventory changed after Laravel optimize.');
        }

        $this->artisan($application, ['optimize:clear'], $environment);
    }

    /**
     * @param array<string, string> $environment
     * @return list<string>
     */
    private function routeInventory(string $application, array $environment): array
    {
        $output = $this->artisan($application, ['route:list', '--json'], $environment);
        $routes = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($routes)) {
            throw new RuntimeException('Laravel route:list did not return an array.');
        }

        $inventory = [];

        foreach ($routes as $route) {
            if (! is_array($route)) {
                throw new RuntimeException('Laravel route:list returned an invalid route entry.');
            }

            $method = $route['method'] ?? null;
            $uri = $route['uri'] ?? null;
            $name = $route['name'] ?? null;

            if (! is_string($method) || ! is_string($uri) || (! is_string($name) && $name !== null)) {
                throw new RuntimeException('Laravel route:list returned an invalid route identity.');
            }

            if (is_string($name) && str_starts_with($name, 'generated::')) {
                $name = null;
            }

            $identity = implode('|', [$method, $uri, $name ?? '']);

            if (isset($inventory[$identity])) {
                throw new RuntimeException("Duplicate route identity [{$identity}] was registered.");
            }

            $inventory[$identity] = $identity;
        }

        ksort($inventory, SORT_STRING);

        return array_values($inventory);
    }

    /**
     * @param list<string> $arguments
     * @param array<string, string> $environment
     */
    private function artisan(string $application, array $arguments, array $environment): string
    {
        return $this->artisanResult($application, $arguments, $environment)['output'];
    }

    /**
     * @param list<string> $arguments
     * @param array<string, string> $environment
     * @param list<int> $allowedExitCodes
     * @return array{exit_code: int, output: string}
     */
    private function artisanResult(
        string $application,
        array $arguments,
        array $environment,
        array $allowedExitCodes = [0],
    ): array {
        return $this->command(
            [PHP_BINARY, 'artisan', ...$arguments, '--no-ansi'],
            $application,
            $environment,
            $allowedExitCodes,
        );
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $environment
     * @param list<int> $allowedExitCodes
     * @return array{exit_code: int, output: string}
     */
    private function command(
        array $command,
        string $directory,
        array $environment,
        array $allowedExitCodes = [0],
    ): array {
        $errorStream = fopen('php://temp', 'w+');

        if ($errorStream === false) {
            throw new RuntimeException('Unable to create an interoperability error stream.');
        }

        $process = proc_open(
            $command,
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['pipe', 'w'],
                2 => $errorStream,
            ],
            $pipes,
            $directory,
            $environment,
            ['bypass_shell' => true],
        );

        if (! is_resource($process)) {
            fclose($errorStream);
            throw new RuntimeException('Unable to start an interoperability command.');
        }

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exitCode = proc_close($process);
        rewind($errorStream);
        $errorOutput = stream_get_contents($errorStream);
        fclose($errorStream);

        $output = ($output === false ? '' : $output).($errorOutput === false ? '' : $errorOutput);
        echo $output;

        if (! in_array($exitCode, $allowedExitCodes, true)) {
            throw new RuntimeException(sprintf(
                'Interoperability command [%s] failed with exit code %d.',
                implode(' ', $command),
                $exitCode,
            ));
        }

        return ['exit_code' => $exitCode, 'output' => $output];
    }

    private function contents(string $path): string
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read [{$path}].");
        }

        return $contents;
    }

    private function assertFileExists(string $path, string $message): void
    {
        if (! is_file($path)) {
            throw new RuntimeException($message);
        }
    }

    private function assertContains(string $expected, string $actual, string $message): void
    {
        if (! str_contains($actual, $expected)) {
            throw new RuntimeException($message);
        }
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $entry */
        foreach ($iterator as $entry) {
            if ($entry->isLink() || $entry->isFile()) {
                unlink($entry->getPathname());
            } else {
                rmdir($entry->getPathname());
            }
        }

        rmdir($path);
    }
}

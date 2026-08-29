<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Exceptions\PackageModuleDiscoveryFailed;
use Cluion\Moduark\Module;
use Cluion\Moduark\Package\ComposerPackageModuleDiscoverer;
use Cluion\Moduark\Package\PackageModuleCatalog;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Inspection\Modules\Billing\BillingModule;
use Tests\Fixtures\LevelThree\Modules\User\UserModule;

final class ComposerPackageModuleDiscovererTest extends TestCase
{
    private string $directory;

    private string $manifest;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/moduark-package-modules-'.bin2hex(random_bytes(8));
        $this->manifest = $this->directory.'/vendor/composer/installed.json';
        self::assertTrue(mkdir(dirname($this->manifest), 0755, true));
    }

    protected function tearDown(): void
    {
        if (is_file($this->manifest)) {
            unlink($this->manifest);
        }

        if (is_dir(dirname($this->manifest))) {
            rmdir(dirname($this->manifest));
        }

        if (is_dir(dirname(dirname($this->manifest)))) {
            rmdir(dirname(dirname($this->manifest)));
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function test_discovers_a_deterministic_scalar_catalog_and_fingerprint(): void
    {
        $packages = [
            $this->package(
                'acme/user-module',
                'User',
                UserModule::class,
                'tests/Fixtures/LevelThree/Modules/User/UserModule.php',
            ),
            $this->package(
                'acme/billing-module',
                'Billing',
                BillingModule::class,
                'tests/Fixtures/Inspection/Modules/Billing/BillingModule.php',
            ),
        ];
        $this->write(array_reverse($packages));

        $first = (new ComposerPackageModuleDiscoverer($this->manifest))->discover();
        $this->write($packages, false);
        $second = (new ComposerPackageModuleDiscoverer($this->manifest))->discover();

        self::assertSame([
            'schema_version' => PackageModuleCatalog::SCHEMA_VERSION,
            'modules' => [
                [
                    'package' => 'acme/billing-module',
                    'name' => 'Billing',
                    'class' => BillingModule::class,
                    'path' => 'tests/Fixtures/Inspection/Modules/Billing/BillingModule.php',
                    'namespace' => 'Tests\\Fixtures\\Inspection\\Modules\\Billing',
                ],
                [
                    'package' => 'acme/user-module',
                    'name' => 'User',
                    'class' => UserModule::class,
                    'path' => 'tests/Fixtures/LevelThree/Modules/User/UserModule.php',
                    'namespace' => 'Tests\\Fixtures\\LevelThree\\Modules\\User',
                ],
            ],
        ], $first->toArray());
        self::assertSame($first->toArray(), $second->toArray());
        self::assertSame($first->fingerprint(), $second->fingerprint());
        self::assertSame(64, strlen($first->fingerprint()));
        self::assertSame(
            [BillingModule::class, UserModule::class],
            $first->moduleClasses(),
        );
        self::assertSame(UserModule::class, $first->find('user')?->moduleClass());
        self::assertTrue($first->containsClass(UserModule::class));
        self::assertNull($first->find('Missing'));
        self::assertSame(
            realpath(dirname(__DIR__, 2).'/tests/Fixtures/Inspection/Modules/Billing/BillingModule.php'),
            $first->all()[0]->discoveredModule()->path(),
        );
    }

    public function test_missing_manifest_returns_an_empty_catalog(): void
    {
        $catalog = (new ComposerPackageModuleDiscoverer($this->manifest))->discover();

        self::assertSame([
            'schema_version' => PackageModuleCatalog::SCHEMA_VERSION,
            'modules' => [],
        ], $catalog->toArray());
    }

    public function test_duplicate_package_module_name_fails_closed(): void
    {
        $this->write([
            $this->package(
                'acme/first-user',
                'User',
                UserModule::class,
                'tests/Fixtures/LevelThree/Modules/User/UserModule.php',
            ),
            $this->package(
                'acme/second-user',
                'User',
                \Tests\Fixtures\Analysis\Modules\User\UserModule::class,
                'tests/Fixtures/Analysis/Modules/User/UserModule.php',
            ),
        ]);

        $this->expectException(PackageModuleDiscoveryFailed::class);
        $this->expectExceptionMessage(
            'Duplicate package Module name [User] in [acme/first-user] and [acme/second-user].',
        );

        (new ComposerPackageModuleDiscoverer($this->manifest))->discover();
    }

    public function test_unknown_metadata_schema_fails_closed(): void
    {
        $package = $this->package(
            'acme/user-module',
            'User',
            UserModule::class,
            'tests/Fixtures/LevelThree/Modules/User/UserModule.php',
            999,
        );
        $this->write([$package]);

        $this->expectException(PackageModuleDiscoveryFailed::class);
        $this->expectExceptionMessage('Composer package [acme/user-module] has invalid Moduark metadata.');

        (new ComposerPackageModuleDiscoverer($this->manifest))->discover();
    }

    public function test_duplicate_package_module_class_fails_closed(): void
    {
        $this->write([
            $this->package(
                'acme/first-user',
                'User',
                UserModule::class,
                'tests/Fixtures/LevelThree/Modules/User/UserModule.php',
            ),
            $this->package(
                'acme/second-user',
                'User',
                UserModule::class,
                'tests/Fixtures/LevelThree/Modules/User/UserModule.php',
            ),
        ]);

        $this->expectException(PackageModuleDiscoveryFailed::class);
        $this->expectExceptionMessage(
            'Duplicate package Module class ['.UserModule::class.'] in [acme/first-user] and [acme/second-user].',
        );

        (new ComposerPackageModuleDiscoverer($this->manifest))->discover();
    }

    public function test_descriptor_path_traversal_fails_closed(): void
    {
        $package = $this->package(
            'acme/user-module',
            'User',
            UserModule::class,
            '../UserModule.php',
        );
        $this->write([$package]);

        $this->expectException(PackageModuleDiscoveryFailed::class);
        $this->expectExceptionMessage('identity fields are invalid');

        (new ComposerPackageModuleDiscoverer($this->manifest))->discover();
    }

    /**
     * @param class-string<Module> $moduleClass
     * @return array{
     *     name: string,
     *     install-path: string,
     *     extra: array{
     *         moduark: array{
     *             schema_version: int,
     *             modules: list<array{name: string, class: class-string<Module>, path: string}>
     *         }
     *     }
     * }
     */
    private function package(
        string $package,
        string $name,
        string $moduleClass,
        string $path,
        int $schemaVersion = PackageModuleCatalog::SCHEMA_VERSION,
    ): array {
        return [
            'name' => $package,
            'install-path' => dirname(__DIR__, 2),
            'extra' => [
                'moduark' => [
                    'schema_version' => $schemaVersion,
                    'modules' => [[
                        'name' => $name,
                        'class' => $moduleClass,
                        'path' => $path,
                    ]],
                ],
            ],
        ];
    }

    /** @param list<array<string, mixed>> $packages */
    private function write(array $packages, bool $objectEnvelope = true): void
    {
        $payload = $objectEnvelope ? ['packages' => $packages] : $packages;

        self::assertIsInt(file_put_contents(
            $this->manifest,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        ));
    }
}

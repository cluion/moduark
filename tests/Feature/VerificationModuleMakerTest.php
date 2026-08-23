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
use ParseError;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Tests\TestCase;

final class VerificationModuleMakerTest extends TestCase
{
    private string $temporaryBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryBasePath = sys_get_temp_dir().'/moduark-verification-maker-'
            .bin2hex(random_bytes(8));

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

    public function test_verification_plans_match_the_reviewed_laravel_major_fixture(): void
    {
        $major = (int) explode('.', Application::VERSION, 2)[0];
        $fixture = dirname(__DIR__).'/Fixtures/Generation/verification-types-laravel-'
            .$major.'.json';
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
            [$this->temporaryBasePath.'/app/Modules/User/UserModule.php'],
            $this->files(),
        );
    }

    public function test_it_generates_module_owned_phpunit_feature_and_unit_tests(): void
    {
        $this->command('moduark:make User test Billing/InvoiceFeatureTest')
            ->assertSuccessful();
        $this->command('moduark:make User test Billing/InvoiceUnitTest --unit --phpunit')
            ->assertSuccessful();

        $feature = $this->contents('Tests/Feature/Billing/InvoiceFeatureTest.php');
        $unit = $this->contents('Tests/Unit/Billing/InvoiceUnitTest.php');

        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Tests\\Feature\\Billing;',
            $feature,
        );
        self::assertStringContainsString('use Tests\\TestCase;', $feature);
        self::assertStringContainsString('class InvoiceFeatureTest extends TestCase', $feature);
        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Tests\\Unit\\Billing;',
            $unit,
        );
        self::assertStringContainsString('use PHPUnit\\Framework\\TestCase;', $unit);
        self::assertStringContainsString('class InvoiceUnitTest extends TestCase', $unit);
        $this->assertValidPhp($feature);
        $this->assertValidPhp($unit);
        self::assertDirectoryDoesNotExist($this->temporaryBasePath.'/tests');
    }

    public function test_it_generates_pest_modes_and_explicit_phpunit_takes_precedence(): void
    {
        $this->command('moduark:make User test Billing/InvoicePestTest --pest')
            ->assertSuccessful();
        $this->command('moduark:make User test Billing/InvoicePestUnitTest --unit --pest')
            ->assertSuccessful();
        $this->command(
            'moduark:make User test Billing/ExplicitPhpunitTest --pest --phpunit',
        )->assertSuccessful();

        self::assertStringContainsString(
            "test('example', function ()",
            $this->contents('Tests/Feature/Billing/InvoicePestTest.php'),
        );
        self::assertStringContainsString(
            'expect(true)->toBeTrue();',
            $this->contents('Tests/Unit/Billing/InvoicePestUnitTest.php'),
        );
        $phpunit = $this->contents('Tests/Feature/Billing/ExplicitPhpunitTest.php');
        self::assertStringContainsString('class ExplicitPhpunitTest extends TestCase', $phpunit);
        self::assertStringNotContainsString("test('example'", $phpunit);
        self::assertDirectoryDoesNotExist($this->temporaryBasePath.'/tests');
    }

    public function test_matching_test_is_preflighted_and_force_overwritten_with_its_artifact(): void
    {
        $testPath = $this->path('Tests/Feature/Jobs/Billing/ProcessInvoiceTest.php');
        self::assertTrue(mkdir(dirname($testPath), 0755, true));
        self::assertIsInt(file_put_contents($testPath, 'existing test'));

        $command = 'moduark:make User job Billing/ProcessInvoice --test';
        $artifactPath = $this->path('Jobs/Billing/ProcessInvoice.php');

        $this->command($command)
            ->expectsOutputToContain('Test already exists.')
            ->assertFailed();
        self::assertFileDoesNotExist($artifactPath);
        self::assertSame('existing test', file_get_contents($testPath));

        $this->command($command.' --force')->assertSuccessful();
        self::assertFileExists($artifactPath);
        self::assertNotSame('existing test', file_get_contents($testPath));
    }

    public function test_view_matching_test_uses_the_module_view_namespace(): void
    {
        $this->command('moduark:make User view billing.invoice-summary --phpunit')
            ->assertSuccessful();

        $test = $this->contents('Tests/Feature/View/Billing/InvoiceSummaryTest.php');

        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Tests\\Feature\\View\\Billing;',
            $test,
        );
        self::assertStringContainsString(
            '$this->view(\'user::billing.invoice-summary\'',
            $test,
        );
        self::assertFileExists(
            $this->path('resources/views/billing/invoice-summary.blade.php'),
        );
        self::assertDirectoryDoesNotExist($this->temporaryBasePath.'/tests');
    }

    public function test_anonymous_component_matching_test_is_not_silently_ignored(): void
    {
        $this->command('moduark:make User component Billing/InvoiceBadge --view --test')
            ->assertSuccessful();

        self::assertFileExists(
            $this->path('resources/views/components/billing/invoice-badge.blade.php'),
        );
        self::assertFileExists(
            $this->path('Tests/Feature/View/Components/Billing/InvoiceBadgeTest.php'),
        );
        self::assertFileDoesNotExist(
            $this->path('View/Components/Billing/InvoiceBadge.php'),
        );
        self::assertDirectoryDoesNotExist($this->temporaryBasePath.'/tests');
    }

    public function test_verification_options_reject_foreign_combinations_without_mutation(): void
    {
        $this->command('moduark:make User test Billing/InvoiceTest --test')
            ->expectsOutputToContain(
                'Module Maker failed: The --test option is not supported for Maker type [test].',
            )
            ->assertExitCode(2);
        $this->command('moduark:make User job Billing/ProcessInvoice --unit')
            ->expectsOutputToContain(
                'Module Maker failed: The --unit option is not supported for Maker type [job].',
            )
            ->assertExitCode(2);
        $this->command('moduark:make User class Support/Task --pest')
            ->expectsOutputToContain(
                'Module Maker failed: The --pest option is not supported for Maker type [class].',
            )
            ->assertExitCode(2);
        $this->command('moduark:make User test Billing/InvoiceTest --inline')
            ->expectsOutputToContain(
                'Module Maker failed: The --inline option is not supported for Maker type [test].',
            )
            ->assertExitCode(2);

        self::assertSame(
            [$this->temporaryBasePath.'/app/Modules/User/UserModule.php'],
            $this->files(),
        );
    }

    /** @return array{schema: int, laravel_major: int, plans: list<array{type: string, name: string, options: string, targets: list<string>}>} */
    private function planFixture(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read verification plan fixture [{$path}].");
        }

        /** @var array{schema: int, laravel_major: int, plans: list<array{type: string, name: string, options: string, targets: list<string>}>} $fixture */
        $fixture = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $fixture;
    }

    private function path(string $relativePath): string
    {
        return $this->temporaryBasePath.'/app/Modules/User/'.$relativePath;
    }

    private function contents(string $relativePath): string
    {
        $contents = file_get_contents($this->path($relativePath));

        if ($contents === false) {
            throw new RuntimeException("Unable to read generated target [{$relativePath}].");
        }

        return $contents;
    }

    private function assertValidPhp(string $contents): void
    {
        try {
            self::assertNotEmpty(token_get_all($contents, TOKEN_PARSE));
        } catch (ParseError $error) {
            self::fail($error->getMessage());
        }
    }

    /** @return list<string> */
    private function files(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $this->temporaryBasePath.'/app/Modules/User',
                FilesystemIterator::SKIP_DOTS,
            ),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        sort($files, SORT_STRING);

        return $files;
    }
}

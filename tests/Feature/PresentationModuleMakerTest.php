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

final class PresentationModuleMakerTest extends TestCase
{
    private string $temporaryBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryBasePath = sys_get_temp_dir().'/moduark-presentation-maker-'
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

    public function test_presentation_plans_match_the_reviewed_laravel_major_fixture(): void
    {
        $major = (int) explode('.', Application::VERSION, 2)[0];
        $fixture = dirname(__DIR__).'/Fixtures/Generation/presentation-types-laravel-'
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

    public function test_it_generates_a_module_owned_class_component_and_view(): void
    {
        $this->command('moduark:make User component Billing/InvoiceCard')
            ->assertSuccessful();

        $classPath = $this->temporaryBasePath
            .'/app/Modules/User/View/Components/Billing/InvoiceCard.php';
        $viewPath = $this->temporaryBasePath
            .'/app/Modules/User/resources/views/components/billing/invoice-card.blade.php';
        $class = (string) file_get_contents($classPath);

        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\View\\Components\\Billing;',
            $class,
        );
        self::assertStringContainsString('class InvoiceCard extends Component', $class);
        self::assertStringContainsString("return view('user::components.billing.invoice-card');", $class);
        self::assertSame("<div>\n    <!-- -->\n</div>\n", file_get_contents($viewPath));
        self::assertDirectoryDoesNotExist($this->temporaryBasePath.'/resources/views');
    }

    public function test_it_generates_inline_and_anonymous_component_modes(): void
    {
        $this->command('moduark:make User component Billing/InlineAlert --inline')
            ->assertSuccessful();
        $this->command('moduark:make User component Billing/InvoiceBadge --view')
            ->assertSuccessful();

        $inlinePath = $this->temporaryBasePath
            .'/app/Modules/User/View/Components/Billing/InlineAlert.php';
        $anonymousView = $this->temporaryBasePath
            .'/app/Modules/User/resources/views/components/billing/invoice-badge.blade.php';
        $inline = (string) file_get_contents($inlinePath);

        self::assertStringContainsString("return <<<'blade'", $inline);
        self::assertStringNotContainsString('return view(', $inline);
        self::assertFileDoesNotExist(
            $this->temporaryBasePath
                .'/app/Modules/User/resources/views/components/billing/inline-alert.blade.php',
        );
        self::assertFileExists($anonymousView);
        self::assertFileDoesNotExist(
            $this->temporaryBasePath.'/app/Modules/User/View/Components/Billing/InvoiceBadge.php',
        );
    }

    public function test_component_custom_path_remains_inside_module_views(): void
    {
        $this->command(
            'moduark:make User component Billing/InvoicePanel --path=widgets/admin',
        )->assertSuccessful();

        $class = (string) file_get_contents(
            $this->temporaryBasePath
                .'/app/Modules/User/View/Components/Billing/InvoicePanel.php',
        );

        self::assertStringContainsString("return view('user::widgets.admin.invoice-panel');", $class);
        self::assertFileExists(
            $this->temporaryBasePath
                .'/app/Modules/User/resources/views/widgets/admin/invoice-panel.blade.php',
        );
        self::assertDirectoryDoesNotExist($this->temporaryBasePath.'/resources/views');
    }

    public function test_component_preflights_all_collisions_and_force_overwrites_all_targets(): void
    {
        $viewPath = $this->temporaryBasePath
            .'/app/Modules/User/resources/views/components/billing/invoice-card.blade.php';
        self::assertTrue(mkdir(dirname($viewPath), 0755, true));
        self::assertIsInt(file_put_contents($viewPath, 'existing view'));

        $command = 'moduark:make User component Billing/InvoiceCard';
        $classPath = $this->temporaryBasePath
            .'/app/Modules/User/View/Components/Billing/InvoiceCard.php';

        $this->command($command)
            ->expectsOutputToContain('View already exists.')
            ->assertFailed();
        self::assertFileDoesNotExist($classPath);
        self::assertSame('existing view', file_get_contents($viewPath));

        $this->command($command.' --force')->assertSuccessful();
        self::assertFileExists($classPath);
        self::assertNotSame('existing view', file_get_contents($viewPath));
    }

    public function test_component_rolls_back_class_when_view_write_fails(): void
    {
        $resourcesPath = $this->temporaryBasePath.'/app/Modules/User/resources';
        self::assertTrue(mkdir($resourcesPath, 0755, true));
        self::assertIsInt(file_put_contents($resourcesPath.'/views', 'blocking file'));

        $this->command('moduark:make User component Billing/InvoiceCard')
            ->expectsOutputToContain('Module Maker failed:')
            ->assertExitCode(2);

        self::assertFileDoesNotExist(
            $this->temporaryBasePath
                .'/app/Modules/User/View/Components/Billing/InvoiceCard.php',
        );
        self::assertSame('blocking file', file_get_contents($resourcesPath.'/views'));
    }

    public function test_component_rejects_conflicting_or_unsafe_options_without_mutation(): void
    {
        $this->command(
            'moduark:make User component Billing/InvoiceCard --inline --view',
        )
            ->expectsOutputToContain(
                'Module Maker failed: The component Maker options [--inline, --view] cannot be combined.',
            )
            ->assertExitCode(2);
        $this->command(
            'moduark:make User component Billing/InvoiceCard --inline --path=widgets',
        )
            ->expectsOutputToContain(
                'Module Maker failed: The component Maker options [--inline, --path] cannot be combined.',
            )
            ->assertExitCode(2);
        $this->command(
            'moduark:make User component Billing/InvoiceCard --path=../outside',
        )
            ->expectsOutputToContain(
                'Module Maker failed: Component path [../outside] must contain one or more lowercase kebab-case directory segments.',
            )
            ->assertExitCode(2);
        $this->command('moduark:make User component Billing/InvoiceCard --model=Profile')
            ->expectsOutputToContain(
                'Module Maker failed: The --model option is not supported for Maker type [component].',
            )
            ->assertExitCode(2);

        self::assertSame(
            [$this->temporaryBasePath.'/app/Modules/User/UserModule.php'],
            $this->files(),
        );
    }

    public function test_it_generates_module_owned_views_from_dot_and_slash_names(): void
    {
        $this->command('moduark:make User view billing.invoice-summary')
            ->assertSuccessful();
        $this->command('moduark:make User view admin/BillingReport --extension=html')
            ->assertSuccessful();

        self::assertSame(
            "<div>\n    <!-- -->\n</div>\n",
            file_get_contents(
                $this->temporaryBasePath
                    .'/app/Modules/User/resources/views/billing/invoice-summary.blade.php',
            ),
        );
        self::assertFileExists(
            $this->temporaryBasePath
                .'/app/Modules/User/resources/views/admin/billing-report.html',
        );
        self::assertDirectoryDoesNotExist($this->temporaryBasePath.'/resources/views');
    }

    public function test_view_shares_collision_force_and_dry_run_behavior(): void
    {
        $relativePath = 'resources/views/billing/invoice-summary.blade.php';
        $path = $this->temporaryBasePath.'/app/Modules/User/'.$relativePath;
        $command = 'moduark:make User view billing.invoice-summary';

        $this->command($command.' --dry-run')
            ->expectsOutputToContain('CREATE '.$relativePath)
            ->assertSuccessful();
        self::assertFileDoesNotExist($path);

        $this->command($command)->assertSuccessful();
        self::assertIsInt(file_put_contents($path, 'existing view'));

        $this->command($command)
            ->expectsOutputToContain('View already exists.')
            ->assertFailed();
        self::assertSame('existing view', file_get_contents($path));

        $this->command($command.' --force')->assertSuccessful();
        self::assertNotSame('existing view', file_get_contents($path));
    }

    public function test_view_rejects_unsafe_names_extensions_and_foreign_options(): void
    {
        $this->command('moduark:make User view ../outside')
            ->expectsOutputToContain(
                'Module Maker failed: View name [../outside] must contain one or more alphanumeric path segments separated by dots or slashes.',
            )
            ->assertExitCode(2);
        $this->command('moduark:make User view billing.summary --extension=../php')
            ->expectsOutputToContain(
                'Module Maker failed: View extension [../php] must contain one or more lowercase alphanumeric segments separated by dots.',
            )
            ->assertExitCode(2);
        $this->command('moduark:make User view billing.summary --inline')
            ->expectsOutputToContain(
                'Module Maker failed: The --inline option is not supported for Maker type [view].',
            )
            ->assertExitCode(2);
        $this->command('moduark:make User view billing.summary --view')
            ->expectsOutputToContain(
                'Module Maker failed: The --view option is not supported for Maker type [view].',
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
            throw new RuntimeException("Unable to read presentation plan fixture [{$path}].");
        }

        /** @var array{schema: int, laravel_major: int, plans: list<array{type: string, name: string, options: string, targets: list<string>}>} $fixture */
        $fixture = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $fixture;
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

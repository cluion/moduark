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

final class AsyncModuleMakerTest extends TestCase
{
    private string $temporaryBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryBasePath = sys_get_temp_dir().'/moduark-async-maker-'.bin2hex(random_bytes(8));

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

    public function test_async_plans_match_the_reviewed_laravel_major_fixture(): void
    {
        $major = (int) explode('.', Application::VERSION, 2)[0];
        $fixture = dirname(__DIR__).'/Fixtures/Generation/async-types-laravel-'.$major.'.json';
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

    public function test_it_generates_a_nested_module_owned_event_without_related_side_effects(): void
    {
        $this->command('moduark:make User event Billing/InvoicePaid')
            ->assertSuccessful();

        $eventPath = $this->temporaryBasePath.'/app/Modules/User/Events/Billing/InvoicePaid.php';
        $event = (string) file_get_contents($eventPath);

        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Events\\Billing;',
            $event,
        );
        self::assertStringContainsString('use Illuminate\\Foundation\\Events\\Dispatchable;', $event);
        self::assertStringContainsString('use Illuminate\\Queue\\SerializesModels;', $event);
        self::assertStringContainsString('class InvoicePaid', $event);
        self::assertStringContainsString('use Dispatchable, InteractsWithSockets, SerializesModels;', $event);
        self::assertStringContainsString('public function broadcastOn(): array', $event);
        self::assertDirectoryDoesNotExist($this->temporaryBasePath.'/app/Modules/User/Listeners');
        self::assertDirectoryDoesNotExist($this->temporaryBasePath.'/app/Modules/User/Providers');
        self::assertSame([
            $eventPath,
            $this->temporaryBasePath.'/app/Modules/User/UserModule.php',
        ], $this->files());
    }

    public function test_event_shares_collision_force_and_dry_run_behavior(): void
    {
        $relativePath = 'Events/Billing/InvoicePaid.php';
        $path = $this->temporaryBasePath.'/app/Modules/User/'.$relativePath;
        $command = 'moduark:make User event Billing/InvoicePaid';

        $this->command($command.' --dry-run')
            ->expectsOutputToContain('CREATE '.$relativePath)
            ->assertSuccessful();
        self::assertFileDoesNotExist($path);

        $this->command($command)->assertSuccessful();
        self::assertIsInt(file_put_contents($path, 'existing source'));

        $this->command($command)
            ->expectsOutputToContain('Event already exists.')
            ->assertFailed();
        self::assertSame('existing source', file_get_contents($path));

        $this->command($command.' --force')->assertSuccessful();
        self::assertNotSame('existing source', file_get_contents($path));
    }

    public function test_event_rejects_foreign_options_without_filesystem_mutation(): void
    {
        $this->command('moduark:make User event Billing/InvoicePaid --model=Profile')
            ->expectsOutputToContain(
                'Module Maker failed: The --model option is not supported for Maker type [event].',
            )
            ->assertExitCode(2);

        self::assertSame(
            [$this->temporaryBasePath.'/app/Modules/User/UserModule.php'],
            $this->files(),
        );
    }

    public function test_it_generates_plain_typed_and_queued_module_owned_listeners(): void
    {
        $this->command('moduark:make User event Billing/InvoicePaid')->assertSuccessful();
        $this->command('moduark:make User listener Billing/RecordInvoicePayment')->assertSuccessful();
        $this->command(
            'moduark:make User listener Billing/SendInvoiceReceipt --event=Billing/InvoicePaid',
        )->assertSuccessful();
        $this->command(
            'moduark:make User listener Billing/QueueInvoiceReceipt --queued',
        )->assertSuccessful();
        $this->command(
            'moduark:make User listener Billing/NotifyAccounting --event=Billing/InvoicePaid --queued',
        )->assertSuccessful();

        $plainPath = $this->temporaryBasePath
            .'/app/Modules/User/Listeners/Billing/RecordInvoicePayment.php';
        $typedPath = $this->temporaryBasePath
            .'/app/Modules/User/Listeners/Billing/SendInvoiceReceipt.php';
        $plainQueuedPath = $this->temporaryBasePath
            .'/app/Modules/User/Listeners/Billing/QueueInvoiceReceipt.php';
        $queuedPath = $this->temporaryBasePath
            .'/app/Modules/User/Listeners/Billing/NotifyAccounting.php';
        $plain = (string) file_get_contents($plainPath);
        $typed = (string) file_get_contents($typedPath);
        $plainQueued = (string) file_get_contents($plainQueuedPath);
        $queued = (string) file_get_contents($queuedPath);

        foreach ([$plain, $typed, $plainQueued, $queued] as $listener) {
            self::assertStringContainsString(
                'namespace MakerFixture\\Modules\\User\\Listeners\\Billing;',
                $listener,
            );
        }

        self::assertStringContainsString('public function handle(object $event): void', $plain);
        self::assertStringContainsString(
            'use MakerFixture\\Modules\\User\\Events\\Billing\\InvoicePaid;',
            $typed,
        );
        self::assertStringContainsString('public function handle(InvoicePaid $event): void', $typed);
        self::assertStringContainsString(
            'class QueueInvoiceReceipt implements ShouldQueue',
            $plainQueued,
        );
        self::assertStringContainsString('public function handle(object $event): void', $plainQueued);
        self::assertStringContainsString('class NotifyAccounting implements ShouldQueue', $queued);
        self::assertStringContainsString('use InteractsWithQueue;', $queued);
        self::assertStringContainsString(
            'use MakerFixture\\Modules\\User\\Events\\Billing\\InvoicePaid;',
            $queued,
        );
        self::assertDirectoryDoesNotExist($this->temporaryBasePath.'/app/Events');
        self::assertDirectoryDoesNotExist($this->temporaryBasePath.'/app/Providers');
    }

    public function test_listener_shares_collision_force_and_dry_run_behavior(): void
    {
        $relativePath = 'Listeners/Billing/SendInvoiceReceipt.php';
        $path = $this->temporaryBasePath.'/app/Modules/User/'.$relativePath;
        $command = 'moduark:make User listener Billing/SendInvoiceReceipt'
            .' --event=Billing/InvoicePaid';

        $this->command($command.' --dry-run')
            ->expectsOutputToContain('CREATE '.$relativePath)
            ->assertSuccessful();
        self::assertFileDoesNotExist($path);

        $this->command($command)->assertSuccessful();
        self::assertIsInt(file_put_contents($path, 'existing source'));

        $this->command($command)
            ->expectsOutputToContain('Listener already exists.')
            ->assertFailed();
        self::assertSame('existing source', file_get_contents($path));

        $this->command($command.' --force')->assertSuccessful();
        self::assertNotSame('existing source', file_get_contents($path));
    }

    public function test_listener_rejects_foreign_or_unsafe_event_options_without_mutation(): void
    {
        $this->command('moduark:make User listener Billing/SendInvoiceReceipt --model=Profile')
            ->expectsOutputToContain(
                'Module Maker failed: The --model option is not supported for Maker type [listener].',
            )
            ->assertExitCode(2);
        $this->command(
            'moduark:make User listener Billing/SendInvoiceReceipt --event=/App/Events/InvoicePaid',
        )
            ->expectsOutputToContain(
                'Module Maker failed: Listener event [/App/Events/InvoicePaid] must contain one or more StudlyCase class segments relative to the Module Events namespace.',
            )
            ->assertExitCode(2);
        $this->command(
            'moduark:make User listener Billing/SendInvoiceReceipt --event=invoicePaid',
        )
            ->expectsOutputToContain(
                'Module Maker failed: Listener event [invoicePaid] must contain one or more StudlyCase class segments relative to the Module Events namespace.',
            )
            ->assertExitCode(2);

        self::assertSame(
            [$this->temporaryBasePath.'/app/Modules/User/UserModule.php'],
            $this->files(),
        );
    }

    public function test_it_generates_queued_sync_and_batched_module_owned_jobs(): void
    {
        $this->command('moduark:make User job Billing/ProcessInvoice')->assertSuccessful();
        $this->command('moduark:make User job Billing/SyncInvoice --sync')->assertSuccessful();
        $this->command(
            'moduark:make User job Billing/ReconcileInvoices --batched',
        )->assertSuccessful();

        $queuedPath = $this->temporaryBasePath
            .'/app/Modules/User/Jobs/Billing/ProcessInvoice.php';
        $syncPath = $this->temporaryBasePath
            .'/app/Modules/User/Jobs/Billing/SyncInvoice.php';
        $batchedPath = $this->temporaryBasePath
            .'/app/Modules/User/Jobs/Billing/ReconcileInvoices.php';
        $queued = (string) file_get_contents($queuedPath);
        $sync = (string) file_get_contents($syncPath);
        $batched = (string) file_get_contents($batchedPath);

        foreach ([$queued, $sync, $batched] as $job) {
            self::assertStringContainsString(
                'namespace MakerFixture\\Modules\\User\\Jobs\\Billing;',
                $job,
            );
        }

        self::assertStringContainsString('class ProcessInvoice implements ShouldQueue', $queued);
        self::assertStringContainsString('use Queueable;', $queued);
        self::assertStringContainsString('use Illuminate\\Foundation\\Bus\\Dispatchable;', $sync);
        self::assertStringContainsString('class SyncInvoice', $sync);
        self::assertStringNotContainsString('implements ShouldQueue', $sync);
        self::assertStringContainsString('class ReconcileInvoices implements ShouldQueue', $batched);
        self::assertStringContainsString('use Batchable, Queueable;', $batched);
        self::assertStringContainsString('if ($this->batch()->cancelled())', $batched);
        self::assertDirectoryDoesNotExist($this->temporaryBasePath.'/tests');
        self::assertDirectoryDoesNotExist($this->temporaryBasePath.'/database');
    }

    public function test_job_shares_collision_force_and_dry_run_behavior(): void
    {
        $relativePath = 'Jobs/Billing/ProcessInvoice.php';
        $path = $this->temporaryBasePath.'/app/Modules/User/'.$relativePath;
        $command = 'moduark:make User job Billing/ProcessInvoice';

        $this->command($command.' --dry-run')
            ->expectsOutputToContain('CREATE '.$relativePath)
            ->assertSuccessful();
        self::assertFileDoesNotExist($path);

        $this->command($command)->assertSuccessful();
        self::assertIsInt(file_put_contents($path, 'existing source'));

        $this->command($command)
            ->expectsOutputToContain('Job already exists.')
            ->assertFailed();
        self::assertSame('existing source', file_get_contents($path));

        $this->command($command.' --force')->assertSuccessful();
        self::assertNotSame('existing source', file_get_contents($path));
    }

    public function test_job_rejects_conflicting_or_foreign_options_without_mutation(): void
    {
        $this->command('moduark:make User job Billing/ProcessInvoice --sync --batched')
            ->expectsOutputToContain(
                'Module Maker failed: The job Maker options [--sync, --batched] cannot be combined.',
            )
            ->assertExitCode(2);
        $this->command('moduark:make User job Billing/ProcessInvoice --event=Billing/InvoicePaid')
            ->expectsOutputToContain(
                'Module Maker failed: The --event option is not supported for Maker type [job].',
            )
            ->assertExitCode(2);

        self::assertSame(
            [$this->temporaryBasePath.'/app/Modules/User/UserModule.php'],
            $this->files(),
        );
    }

    public function test_it_generates_a_plain_module_owned_notification_without_related_views(): void
    {
        $this->command('moduark:make User notification Billing/InvoicePaid')
            ->assertSuccessful();

        $path = $this->temporaryBasePath
            .'/app/Modules/User/Notifications/Billing/InvoicePaid.php';
        $notification = (string) file_get_contents($path);

        self::assertStringContainsString(
            'namespace MakerFixture\\Modules\\User\\Notifications\\Billing;',
            $notification,
        );
        self::assertStringContainsString('class InvoicePaid extends Notification', $notification);
        self::assertStringContainsString('use Queueable;', $notification);
        self::assertStringContainsString("return ['mail'];", $notification);
        self::assertDirectoryDoesNotExist($this->temporaryBasePath.'/resources/views');
        self::assertSame([
            $path,
            $this->temporaryBasePath.'/app/Modules/User/UserModule.php',
        ], $this->files());
    }

    public function test_notification_shares_collision_force_and_dry_run_behavior(): void
    {
        $relativePath = 'Notifications/Billing/InvoicePaid.php';
        $path = $this->temporaryBasePath.'/app/Modules/User/'.$relativePath;
        $command = 'moduark:make User notification Billing/InvoicePaid';

        $this->command($command.' --dry-run')
            ->expectsOutputToContain('CREATE '.$relativePath)
            ->assertSuccessful();
        self::assertFileDoesNotExist($path);

        $this->command($command)->assertSuccessful();
        self::assertIsInt(file_put_contents($path, 'existing source'));

        $this->command($command)
            ->expectsOutputToContain('Notification already exists.')
            ->assertFailed();
        self::assertSame('existing source', file_get_contents($path));

        $this->command($command.' --force')->assertSuccessful();
        self::assertNotSame('existing source', file_get_contents($path));
    }

    public function test_notification_rejects_markdown_and_foreign_options_without_mutation(): void
    {
        $this->command(
            'moduark:make User notification Billing/InvoicePaid --markdown=mail.invoice-paid',
        )
            ->expectsOutputToContain(
                'Module Maker failed: The --markdown option is not supported for Maker type [notification].',
            )
            ->assertExitCode(2);
        $this->command('moduark:make User notification Billing/InvoicePaid --queued')
            ->expectsOutputToContain(
                'Module Maker failed: The --queued option is not supported for Maker type [notification].',
            )
            ->assertExitCode(2);

        self::assertDirectoryDoesNotExist($this->temporaryBasePath.'/resources/views');
        self::assertSame(
            [$this->temporaryBasePath.'/app/Modules/User/UserModule.php'],
            $this->files(),
        );
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
            throw new RuntimeException("Async plan fixture [{$path}] has an invalid root shape.");
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
                throw new RuntimeException("Async plan fixture [{$path}] has an invalid plan.");
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

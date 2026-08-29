<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Capabilities\CapabilityResolver;
use Cluion\Moduark\Discovery\ModuleActivationSet;
use Cluion\Moduark\Discovery\ModuleDiscoverer;
use Cluion\Moduark\Exceptions\ModuleActivationMutationFailed;
use Cluion\Moduark\Lifecycle\Activation\AtomicFileWriter;
use Cluion\Moduark\Lifecycle\Activation\FileModuleActivationStore;
use Cluion\Moduark\Lifecycle\Activation\ModuleActivationDriver;
use Cluion\Moduark\Lifecycle\Activation\ModuleActivationIntent;
use Cluion\Moduark\Lifecycle\Activation\ModuleActivationPlan;
use Cluion\Moduark\Lifecycle\Activation\ModuleActivationPlanner;
use Cluion\Moduark\Lifecycle\Activation\NativeAtomicFileWriter;
use Cluion\Moduark\Lifecycle\ModuleOrderer;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Registry\ModuleRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FileModuleActivationStoreTest extends TestCase
{
    private string $directory;

    private string $statePath;

    private string $lockPath;

    private ModuleRegistry $inventory;

    private ModuleActivationPlanner $planner;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/moduark-activation-store-'.bin2hex(random_bytes(8));
        $this->statePath = $this->directory.'/state.json';
        $this->lockPath = $this->directory.'/state.lock';
        $this->inventory = (new ModuleDiscoverer)->discover(
            dirname(__DIR__).'/Fixtures/Lifecycle/Activation/Modules',
        );
        $this->planner = new ModuleActivationPlanner(
            new ModuleMetadataCompiler,
            new ModuleOrderer,
            new CapabilityResolver,
        );
    }

    protected function tearDown(): void
    {
        foreach ([$this->statePath, $this->lockPath] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function test_missing_file_preserves_each_driver_default(): void
    {
        $standalone = $this->store(ModuleActivationDriver::Standalone);
        $nwidart = $this->store(ModuleActivationDriver::Nwidart);
        $known = $this->knownNames();

        self::assertSame('all:v1', $standalone->load($known)->fingerprint());
        self::assertSame(ModuleActivationSet::only([])->fingerprint(), $nwidart->load($known)->fingerprint());
    }

    public function test_standalone_commit_is_deterministic_and_loads_the_new_state(): void
    {
        $this->writeStandalone($this->baselineStates());
        $store = $this->store(ModuleActivationDriver::Standalone);
        $plan = $this->disableReportsPlan();
        $invalidations = 0;

        $store->commit(
            $this->inventory,
            $this->baseline(),
            $plan,
            static function () use (&$invalidations): void {
                $invalidations++;
            },
        );

        self::assertSame(1, $invalidations);
        self::assertSame([
            'schema_version' => 1,
            'modules' => [
                'AlternativePayments' => false,
                'CycleAlpha' => false,
                'CycleBeta' => false,
                'Foundation' => true,
                'Orders' => true,
                'Reports' => false,
                'Stripe' => true,
            ],
        ], json_decode((string) file_get_contents($this->statePath), true, 512, JSON_THROW_ON_ERROR));
        self::assertFalse($store->load($this->knownNames())->includes('Reports'));
        self::assertSame($plan->activationFingerprint(), $store->load($this->knownNames())->fingerprint());
    }

    public function test_nwidart_commit_preserves_the_flat_status_map(): void
    {
        $this->writeJson($this->baselineStates());
        $store = $this->store(ModuleActivationDriver::Nwidart);

        $store->commit($this->inventory, $this->baseline(), $this->disableReportsPlan(), static function (): void {});

        $payload = json_decode((string) file_get_contents($this->statePath), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertArrayNotHasKey('schema_version', $payload);
        self::assertFalse($payload['Reports']);
    }

    public function test_concurrent_change_is_rejected_before_cache_invalidation(): void
    {
        $changed = $this->baselineStates();
        $changed['Reports'] = false;
        $this->writeStandalone($changed);
        $invalidated = false;

        try {
            $this->store(ModuleActivationDriver::Standalone)->commit(
                $this->inventory,
                $this->baseline(),
                $this->disableReportsPlan(),
                static function () use (&$invalidated): void {
                    $invalidated = true;
                },
            );
            self::fail('Expected a concurrent-state failure.');
        } catch (ModuleActivationMutationFailed $exception) {
            self::assertStringContainsString('changed after planning', $exception->getMessage());
        }

        self::assertFalse($invalidated);
    }

    public function test_cache_failure_preserves_the_previous_state_bytes(): void
    {
        $this->writeStandalone($this->baselineStates());
        $before = file_get_contents($this->statePath);

        try {
            $this->store(ModuleActivationDriver::Standalone)->commit(
                $this->inventory,
                $this->baseline(),
                $this->disableReportsPlan(),
                static function (): void {
                    throw new RuntimeException('cache failure');
                },
            );
            self::fail('Expected a cache failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('cache failure', $exception->getMessage());
        }

        self::assertSame($before, file_get_contents($this->statePath));
    }

    public function test_atomic_write_failure_preserves_the_previous_state_bytes(): void
    {
        $this->writeStandalone($this->baselineStates());
        $before = file_get_contents($this->statePath);
        $writer = new class implements AtomicFileWriter
        {
            public function write(string $path, string $contents): void
            {
                throw ModuleActivationMutationFailed::write($path);
            }
        };

        try {
            $this->store(ModuleActivationDriver::Standalone, $writer)->commit(
                $this->inventory,
                $this->baseline(),
                $this->disableReportsPlan(),
                static function (): void {},
            );
            self::fail('Expected an atomic-write failure.');
        } catch (ModuleActivationMutationFailed $exception) {
            self::assertStringContainsString('atomically write', $exception->getMessage());
        }

        self::assertSame($before, file_get_contents($this->statePath));
    }

    private function store(
        ModuleActivationDriver $driver,
        ?AtomicFileWriter $writer = null,
    ): FileModuleActivationStore {
        return new FileModuleActivationStore(
            $this->statePath,
            $this->lockPath,
            $driver,
            $writer ?? new NativeAtomicFileWriter,
        );
    }

    private function disableReportsPlan(): ModuleActivationPlan
    {
        return $this->planner->plan(
            $this->inventory,
            $this->baseline(),
            'Reports',
            ModuleActivationIntent::Disable,
        );
    }

    private function baseline(): ModuleActivationSet
    {
        return ModuleActivationSet::fromStates($this->baselineStates());
    }

    /** @return array<string, bool> */
    private function baselineStates(): array
    {
        return [
            'AlternativePayments' => false,
            'CycleAlpha' => false,
            'CycleBeta' => false,
            'Foundation' => true,
            'Orders' => true,
            'Reports' => true,
            'Stripe' => true,
        ];
    }

    /** @return list<string> */
    private function knownNames(): array
    {
        return array_column($this->inventory->toArray(), 'name');
    }

    /** @param array<string, bool> $states */
    private function writeStandalone(array $states): void
    {
        $this->writeJson(['schema_version' => 1, 'modules' => $states]);
    }

    /** @param array<mixed> $payload */
    private function writeJson(array $payload): void
    {
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }

        file_put_contents($this->statePath, json_encode($payload, JSON_THROW_ON_ERROR));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use Benchmarks\GenerationBenchmark;
use Benchmarks\GenerationPerformanceGate;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

final class GenerationBenchmarkTest extends TestCase
{
    public function test_it_measures_and_verifies_real_full_scaffold_generation(): void
    {
        $result = (new GenerationBenchmark)->run(2, 0, 2);

        self::assertSame('full-scaffold', $result['fixture']);
        self::assertSame(2, $result['modules']);
        self::assertSame(14, $result['targets_per_module']);
        self::assertSame(28, $result['targets']);
        self::assertSame(0, $result['warmups']);
        self::assertSame(2, $result['iterations']);
        self::assertCount(2, $result['samples']);

        foreach ($result['samples'] as $sample) {
            self::assertSame(28, $sample['verified_targets']);
            self::assertSame(0, $sample['collisions']);
            self::assertSame(0, $sample['artisan_delegates']);
            self::assertGreaterThanOrEqual(0.0, $sample['planning_ms']);
            self::assertGreaterThanOrEqual(0.0, $sample['preflight_ms']);
            self::assertGreaterThanOrEqual(0.0, $sample['execution_ms']);
            self::assertGreaterThan(0.0, $sample['total_ms']);
            self::assertGreaterThan(0.0, $sample['targets_per_second']);
        }

        foreach ($result['summary'] as $summary) {
            self::assertGreaterThanOrEqual(0.0, $summary['min']);
            self::assertGreaterThanOrEqual($summary['min'], $summary['median']);
            self::assertGreaterThanOrEqual($summary['median'], $summary['max']);
        }
    }

    #[DataProvider('invalidBenchmarkCases')]
    public function test_it_rejects_invalid_fixture_dimensions(
        int $modules,
        int $warmups,
        int $iterations,
        string $message,
    ): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        (new GenerationBenchmark)->run($modules, $warmups, $iterations);
    }

    /** @return iterable<string, array{int, int, int, string}> */
    public static function invalidBenchmarkCases(): iterable
    {
        yield 'no Modules' => [0, 0, 1, 'Generation benchmark modules must be at least 1.'];
        yield 'negative warmup' => [1, -1, 1, 'Generation benchmark warmups cannot be negative.'];
        yield 'no measured run' => [1, 0, 0, 'Generation benchmark iterations must be at least 1.'];
    }

    public function test_gate_has_deterministic_pass_fail_and_non_enforced_boundaries(): void
    {
        $gate = new GenerationPerformanceGate;

        self::assertSame('not_enforced', $gate->evaluate(5000.001, 5000.0, false)['status']);
        self::assertSame('passed', $gate->evaluate(5000.0, 5000.0, true)['status']);
        self::assertSame('failed', $gate->evaluate(5000.001, 5000.0, true)['status']);
        self::assertSame(-0.001, $gate->evaluate(5000.001, 5000.0, true)['headroom_ms']);
    }

    #[DataProvider('invalidGateCases')]
    public function test_gate_rejects_invalid_measurements(
        float $observed,
        float $budget,
        string $message,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        (new GenerationPerformanceGate)->evaluate($observed, $budget, true);
    }

    /** @return iterable<string, array{float, float, string}> */
    public static function invalidGateCases(): iterable
    {
        yield 'negative observation' => [-0.001, 5000.0, 'Observed generation median cannot be negative.'];
        yield 'zero budget' => [1.0, 0.0, 'Generation performance budget must be greater than zero.'];
    }

    public function test_runner_exposes_machine_gate_and_exit_codes(): void
    {
        $passed = $this->runner('100000');

        self::assertSame(0, $passed->getExitCode());
        $payload = json_decode($passed->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame(1, $payload['schema_version']);
        self::assertSame('generation', $payload['benchmark']);
        self::assertIsArray($payload['fixture']);
        self::assertSame(14, $payload['fixture']['targets']);
        self::assertIsArray($payload['gate']);
        self::assertTrue($payload['gate']['enforced']);
        self::assertSame('passed', $payload['gate']['status']);

        $failed = $this->runner('0.000001');

        self::assertSame(1, $failed->getExitCode());
        $failedPayload = json_decode($failed->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($failedPayload);
        self::assertIsArray($failedPayload['gate'] ?? null);
        self::assertSame('failed', $failedPayload['gate']['status']);

        $invalid = new Process([
            PHP_BINARY,
            dirname(__DIR__, 2).'/benchmarks/generation.php',
            '--modules=0',
        ]);
        $invalid->run();

        self::assertSame(2, $invalid->getExitCode());
        self::assertStringContainsString(
            'The --modules option must be a positive integer.',
            $invalid->getErrorOutput(),
        );
    }

    private function runner(string $budget): Process
    {
        $process = new Process([
            PHP_BINARY,
            dirname(__DIR__, 2).'/benchmarks/generation.php',
            '--modules=1',
            '--warmups=0',
            '--iterations=1',
            '--max-median-ms='.$budget,
            '--format=json',
            '--enforce',
        ]);
        $process->run();

        return $process;
    }
}

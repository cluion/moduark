<?php

declare(strict_types=1);

namespace Tests\Unit;

use Benchmarks\ArchitectureBenchmark;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ArchitectureBenchmarkTest extends TestCase
{
    public function test_it_measures_a_generated_complete_level_one_check(): void
    {
        $result = (new ArchitectureBenchmark)->run(2, 4, 0, 2);

        self::assertSame(2, $result['modules']);
        self::assertSame(8, $result['php_files']);
        self::assertSame(4, $result['files_per_module']);
        self::assertSame(0, $result['warmups']);
        self::assertSame(2, $result['iterations']);
        self::assertSame(6, $result['rules']);
        self::assertCount(2, $result['samples']);

        foreach ($result['summary'] as $summary) {
            self::assertGreaterThanOrEqual(0.0, $summary['min']);
            self::assertGreaterThanOrEqual($summary['min'], $summary['median']);
            self::assertGreaterThanOrEqual($summary['median'], $summary['max']);
        }
    }

    #[DataProvider('invalidCases')]
    public function test_it_rejects_invalid_fixture_dimensions(
        int $modules,
        int $filesPerModule,
        int $warmups,
        int $iterations,
        string $message,
    ): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        (new ArchitectureBenchmark)->run($modules, $filesPerModule, $warmups, $iterations);
    }

    /**
     * @return iterable<string, array{int, int, int, int, string}>
     */
    public static function invalidCases(): iterable
    {
        yield 'no Modules' => [0, 2, 0, 1, 'Benchmark modules must be at least 1.'];
        yield 'not enough files' => [1, 1, 0, 1, 'Benchmark files per Module must be at least 2.'];
        yield 'negative warmup' => [1, 2, -1, 1, 'Benchmark warmups cannot be negative.'];
        yield 'no measured run' => [1, 2, 0, 0, 'Benchmark iterations must be at least 1.'];
    }
}

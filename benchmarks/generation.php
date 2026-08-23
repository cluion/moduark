<?php

declare(strict_types=1);

use Benchmarks\GenerationBenchmark;
use Benchmarks\GenerationPerformanceGate;

require dirname(__DIR__).'/vendor/autoload.php';

/**
 * @return int
 */
function generationPositiveInteger(string $name, mixed $value, int $default): int
{
    if ($value === false) {
        return $default;
    }

    if (! is_string($value) || preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) {
        throw new InvalidArgumentException("The --{$name} option must be a positive integer.");
    }

    return (int) $value;
}

/**
 * @return int
 */
function generationNonNegativeInteger(string $name, mixed $value, int $default): int
{
    if ($value === false) {
        return $default;
    }

    if (! is_string($value) || preg_match('/\A(?:0|[1-9][0-9]*)\z/', $value) !== 1) {
        throw new InvalidArgumentException("The --{$name} option must be a non-negative integer.");
    }

    return (int) $value;
}

/**
 * @return float
 */
function generationPositiveNumber(string $name, mixed $value, float $default): float
{
    if ($value === false) {
        return $default;
    }

    if (! is_string($value) || ! is_numeric($value) || (float) $value <= 0) {
        throw new InvalidArgumentException("The --{$name} option must be a positive number.");
    }

    return (float) $value;
}

$options = getopt('', [
    'modules:',
    'warmups:',
    'iterations:',
    'max-median-ms:',
    'format:',
    'enforce',
    'help',
]);

if ($options === false) {
    fwrite(STDERR, "Unable to parse generation benchmark options.\n");
    exit(2);
}

if (array_key_exists('help', $options)) {
    echo <<<'HELP'
Usage: php benchmarks/generation.php [options]

  --modules=100           Full-preset Modules generated per sample
  --warmups=1             Unmeasured warm runs
  --iterations=3          Measured runs
  --max-median-ms=5000    Maximum median total time when enforced
  --format=text|json      Output format
  --enforce               Fail when the median total exceeds the budget

HELP;
    exit(0);
}

try {
    $modules = generationPositiveInteger('modules', $options['modules'] ?? false, 100);
    $warmups = generationNonNegativeInteger('warmups', $options['warmups'] ?? false, 1);
    $iterations = generationPositiveInteger('iterations', $options['iterations'] ?? false, 3);
    $budget = generationPositiveNumber(
        'max-median-ms',
        $options['max-median-ms'] ?? false,
        GenerationPerformanceGate::DEFAULT_MAX_MEDIAN_TOTAL_MS,
    );
    $format = $options['format'] ?? 'text';
    $enforced = array_key_exists('enforce', $options);

    if (! is_string($format) || ! in_array($format, ['text', 'json'], true)) {
        throw new InvalidArgumentException('The --format option must be text or json.');
    }

    $benchmark = (new GenerationBenchmark)->run($modules, $warmups, $iterations);
    $gate = (new GenerationPerformanceGate)->evaluate(
        $benchmark['summary']['total_ms']['median'],
        $budget,
        $enforced,
    );
    $result = [
        'schema_version' => 1,
        'benchmark' => 'generation',
        'php_version' => PHP_VERSION,
        'os' => PHP_OS_FAMILY,
        'fixture' => $benchmark,
        'gate' => $gate,
    ];

    if ($format === 'json') {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
    } else {
        echo "Moduark generation performance benchmark\n";
        echo "PHP {$result['php_version']} / {$result['os']}\n";
        printf(
            "%d full-preset Modules / %d template targets / %d measured runs\n",
            $benchmark['modules'],
            $benchmark['targets'],
            $benchmark['iterations'],
        );

        foreach ([
            'planning_ms' => 'Planning',
            'preflight_ms' => 'Preflight',
            'execution_ms' => 'Execution',
            'total_ms' => 'Total',
        ] as $metric => $label) {
            $summary = $benchmark['summary'][$metric];
            printf(
                "  %-10s median %9.3f ms  min %9.3f ms  max %9.3f ms\n",
                $label,
                $summary['median'],
                $summary['min'],
                $summary['max'],
            );
        }

        printf(
            "  Throughput median %.3f targets/second\n",
            $benchmark['summary']['targets_per_second']['median'],
        );
        printf(
            "Gate: %s; median %.3f ms; budget %.3f ms; headroom %.3f ms\n",
            $gate['status'],
            $gate['observed_median_total_ms'],
            $gate['max_median_total_ms'],
            $gate['headroom_ms'],
        );
    }

    exit($gate['status'] === 'failed' ? 1 : 0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Generation benchmark failed: '.$exception->getMessage().PHP_EOL);
    exit(2);
}

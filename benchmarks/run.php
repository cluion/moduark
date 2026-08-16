<?php

declare(strict_types=1);

use Benchmarks\ArchitectureBenchmark;

require dirname(__DIR__).'/vendor/autoload.php';

/**
 * @return int
 */
function positiveInteger(string $name, mixed $value, int $default): int
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
function nonNegativeInteger(string $name, mixed $value, int $default): int
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
 * @return list<int>
 */
function moduleCounts(mixed $value): array
{
    $value = $value === false ? '50,100' : $value;

    if (! is_string($value) || preg_match('/\A[1-9][0-9]*(?:,[1-9][0-9]*)*\z/', $value) !== 1) {
        throw new InvalidArgumentException('The --modules option must be a comma-separated list of positive integers.');
    }

    return array_map(static fn (string $count): int => (int) $count, explode(',', $value));
}

/**
 * @param array{
 *     modules: int,
 *     php_files: int,
 *     files_per_module: int,
 *     warmups: int,
 *     iterations: int,
 *     rules: int,
 *     analysis_cache: 'content-hash',
 *     samples: list<array{discovery_ms: float, check_ms: float, total_ms: float}>,
 *     summary: array{
 *         discovery_ms: array{min: float, median: float, max: float},
 *         check_ms: array{min: float, median: float, max: float},
 *         total_ms: array{min: float, median: float, max: float}
 *     }
 * } $case
 */
function renderCase(array $case): void
{
    printf("\n%d Modules / %d PHP files / %d Level 1 rules\n", $case['modules'], $case['php_files'], $case['rules']);
    printf("  Analysis cache: %s\n", $case['analysis_cache']);

    foreach (['discovery_ms' => 'Discovery', 'check_ms' => 'Level 1 check', 'total_ms' => 'Total'] as $metric => $label) {
        $summary = $case['summary'][$metric];
        printf(
            "  %-13s median %9.3f ms  min %9.3f ms  max %9.3f ms\n",
            $label,
            $summary['median'],
            $summary['min'],
            $summary['max'],
        );
    }
}

$options = getopt('', [
    'modules:',
    'files-per-module:',
    'warmups:',
    'iterations:',
    'format:',
    'help',
]);

if ($options === false) {
    fwrite(STDERR, "Unable to parse benchmark options.\n");
    exit(2);
}

if (array_key_exists('help', $options)) {
    echo <<<'HELP'
Usage: php benchmarks/run.php [options]

  --modules=50,100          Module counts to benchmark
  --files-per-module=100    PHP files generated for each Module
  --warmups=1               Unmeasured warm runs per case
  --iterations=3            Measured runs per case
  --format=text|json        Output format

HELP;
    exit(0);
}

try {
    $modules = moduleCounts($options['modules'] ?? false);
    $filesPerModule = positiveInteger('files-per-module', $options['files-per-module'] ?? false, 100);
    $warmups = nonNegativeInteger('warmups', $options['warmups'] ?? false, 1);
    $iterations = positiveInteger('iterations', $options['iterations'] ?? false, 3);
    $format = $options['format'] ?? 'text';

    if (! is_string($format) || ! in_array($format, ['text', 'json'], true)) {
        throw new InvalidArgumentException('The --format option must be text or json.');
    }

    $benchmark = new ArchitectureBenchmark;
    $cases = [];

    foreach ($modules as $moduleCount) {
        $cases[] = $benchmark->run($moduleCount, $filesPerModule, $warmups, $iterations);
    }

    $result = [
        'php_version' => PHP_VERSION,
        'os' => PHP_OS_FAMILY,
        'mode' => $warmups > 0 ? 'warm' : 'cold',
        'thresholds_enforced' => false,
        'cases' => $cases,
    ];

    if ($format === 'json') {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
        exit(0);
    }

    echo "Moduark architecture performance baseline\n";
    echo "PHP {$result['php_version']} / {$result['os']} / {$result['mode']} run\n";
    echo "No pass/fail timing threshold is enforced.\n";

    foreach ($cases as $case) {
        renderCase($case);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Benchmark failed: '.$exception->getMessage().PHP_EOL);
    exit(2);
}

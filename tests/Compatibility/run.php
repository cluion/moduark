<?php

declare(strict_types=1);

use Tests\Compatibility\DependencyMatrixRunner;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$options = getopt('', ['case:', 'test', 'keep', 'help']);

if ($options === false) {
    fwrite(STDERR, "Unable to parse dependency matrix options.\n");
    exit(2);
}

if (array_key_exists('help', $options)) {
    echo <<<'HELP'
Usage: php tests/Compatibility/run.php [options]

  --case=NAME,...    Dependency matrix cases to resolve
  --test             Install dependencies and execute Architecture, Unit, and Feature suites
  --keep             Preserve generated Composer projects for inspection

Cases:
  laravel-12-lowest
  laravel-12-highest
  laravel-13-lowest
  laravel-13-highest

HELP;
    exit(0);
}

$executeTests = array_key_exists('test', $options);

try {
    $runner = new DependencyMatrixRunner(
        dirname(__DIR__, 2),
        array_key_exists('keep', $options),
        $executeTests,
    );
    $cases = DependencyMatrixRunner::parseCases($options['case'] ?? false);
    $results = $runner->run($cases);

    echo $executeTests
        ? "\nDependency runtime matrix passed:\n"
        : "\nDependency resolution matrix passed:\n";

    foreach ($results as $result) {
        echo sprintf(
            "- %s: platform PHP %s, Laravel %s, Testbench %s, PHPUnit %s%s\n",
            $result['case'],
            $result['php'],
            $result['laravel'],
            $result['testbench'],
            $result['phpunit'],
            $result['tests_executed'] ? ", executed on PHP {$result['runtime_php']}" : '',
        );
    }
} catch (Throwable $exception) {
    $matrix = $executeTests ? 'runtime' : 'resolution';
    fwrite(STDERR, "Dependency {$matrix} matrix failed: ".$exception->getMessage().PHP_EOL);
    exit(2);
}

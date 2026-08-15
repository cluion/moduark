<?php

declare(strict_types=1);

use Tests\Compatibility\DependencyMatrixRunner;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$options = getopt('', ['case:', 'keep', 'help']);

if ($options === false) {
    fwrite(STDERR, "Unable to parse dependency matrix options.\n");
    exit(2);
}

if (array_key_exists('help', $options)) {
    echo <<<'HELP'
Usage: php tests/Compatibility/run.php [options]

  --case=NAME,...    Dependency matrix cases to resolve
  --keep             Preserve generated Composer projects for inspection

Cases:
  laravel-12-lowest
  laravel-12-highest
  laravel-13-lowest
  laravel-13-highest

HELP;
    exit(0);
}

try {
    $runner = new DependencyMatrixRunner(dirname(__DIR__, 2), array_key_exists('keep', $options));
    $cases = DependencyMatrixRunner::parseCases($options['case'] ?? false);
    $results = $runner->run($cases);

    echo "\nDependency resolution matrix passed:\n";

    foreach ($results as $result) {
        echo sprintf(
            "- %s: PHP %s, Laravel %s, Testbench %s, PHPUnit %s\n",
            $result['case'],
            $result['php'],
            $result['laravel'],
            $result['testbench'],
            $result['phpunit'],
        );
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Dependency resolution matrix failed: '.$exception->getMessage().PHP_EOL);
    exit(2);
}

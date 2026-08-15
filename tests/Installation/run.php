<?php

declare(strict_types=1);

use Tests\Installation\CleanApplicationRunner;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$options = getopt('', ['laravel:', 'keep', 'help']);

if ($options === false) {
    fwrite(STDERR, "Unable to parse installation test options.\n");
    exit(2);
}

if (array_key_exists('help', $options)) {
    echo <<<'HELP'
Usage: php tests/Installation/run.php [options]

  --laravel=12,13    Laravel majors to test
  --keep             Preserve generated applications for inspection

HELP;
    exit(0);
}

try {
    $runner = new CleanApplicationRunner(dirname(__DIR__, 2), array_key_exists('keep', $options));
    $majors = CleanApplicationRunner::parseMajors($options['laravel'] ?? false);
    $results = $runner->run($majors);

    echo "\nClean installation matrix passed:\n";

    foreach ($results as $result) {
        echo "- Laravel {$result['major']}: {$result['version']}\n";
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Clean installation matrix failed: '.$exception->getMessage().PHP_EOL);
    exit(2);
}

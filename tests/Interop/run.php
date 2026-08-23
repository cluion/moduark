<?php

declare(strict_types=1);

use Tests\Interop\NwidartApplicationRunner;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$options = getopt('', ['laravel:', 'package:', 'keep', 'help']);

if ($options === false) {
    fwrite(STDERR, "Unable to parse interoperability test options.\n");
    exit(2);
}

if (array_key_exists('help', $options)) {
    echo <<<'HELP'
Usage: php tests/Interop/run.php [options]

  --laravel=MAJORS  Laravel majors to test (default: 12,13)
  --package=VERSION  Install one exact published Packagist version
  --keep             Preserve the generated application for inspection

HELP;
    exit(0);
}

try {
    $runner = new NwidartApplicationRunner(
        dirname(__DIR__, 2),
        NwidartApplicationRunner::parsePackageVersion($options['package'] ?? false),
        array_key_exists('keep', $options),
    );
    $results = $runner->run(
        NwidartApplicationRunner::parseMajors($options['laravel'] ?? false),
    );

    foreach ($results as $result) {
        echo "\nLaravel {$result['laravel']} + nwidart {$result['nwidart']} interoperability installation passed.\n";
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Nwidart interoperability installation failed: '.$exception->getMessage().PHP_EOL);
    exit(2);
}

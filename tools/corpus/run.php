<?php

declare(strict_types=1);

use Tools\Corpus\CorpusAnalyser;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/CorpusAnalyser.php';

$options = getopt('', ['manifest:', 'root:', 'output:', 'help']);

if ($options === false) {
    fwrite(STDERR, "Unable to parse corpus options.\n");
    exit(2);
}

if (array_key_exists('help', $options)) {
    echo <<<'HELP'
Usage: php tools/corpus/run.php --manifest=FILE --root=DIR [--output=FILE]

The manifest selects source roots and namespace grouping. The external Laravel
checkout is never modified; analysis runs against a disposable projection.

HELP;
    exit(0);
}

$manifestPath = $options['manifest'] ?? null;
$root = $options['root'] ?? null;

if (! is_string($manifestPath) || $manifestPath === '' || ! is_file($manifestPath)) {
    fwrite(STDERR, "--manifest must name an existing JSON file.\n");
    exit(2);
}

if (! is_string($root) || $root === '' || ! is_dir($root)) {
    fwrite(STDERR, "--root must name an existing Laravel checkout.\n");
    exit(2);
}

try {
    $contents = file_get_contents($manifestPath);
    $manifest = is_string($contents) ? json_decode($contents, true, flags: JSON_THROW_ON_ERROR) : null;

    if (! is_array($manifest)) {
        throw new RuntimeException('Corpus manifest must decode to a JSON object.');
    }

    if (($manifest['schema'] ?? null) !== 1
        || ! is_string($manifest['name'] ?? null)
        || trim($manifest['name']) === ''
        || ! is_array($manifest['source_roots'] ?? null)
        || ! array_is_list($manifest['source_roots'])
        || $manifest['source_roots'] === []) {
        throw new RuntimeException('Corpus manifest must use schema 1, a name, and source roots.');
    }

    foreach ($manifest['source_roots'] as $sourceRoot) {
        if (! is_array($sourceRoot)
            || ! is_string($sourceRoot['path'] ?? null)
            || trim($sourceRoot['path']) === ''
            || ! is_string($sourceRoot['namespace'] ?? null)
            || trim($sourceRoot['namespace']) === '') {
            throw new RuntimeException('Corpus source roots require non-empty paths and namespaces.');
        }

        $groupBy = $sourceRoot['group_by'] ?? 'single';

        if (! in_array($groupBy, ['first-directory', 'single'], true)) {
            throw new RuntimeException('Corpus source root group_by must be first-directory or single.');
        }

        if (array_key_exists('group', $sourceRoot)
            && (! is_string($sourceRoot['group']) || trim($sourceRoot['group']) === '')) {
            throw new RuntimeException('Corpus source root group must be a non-empty string.');
        }
    }

    $commandRoot = $manifest['command_root'] ?? null;

    if ($commandRoot !== null
        && (! is_array($commandRoot)
            || ! is_string($commandRoot['path'] ?? null)
            || trim($commandRoot['path']) === ''
            || ! is_string($commandRoot['namespace'] ?? null)
            || trim($commandRoot['namespace']) === '')) {
        throw new RuntimeException('Corpus command root requires a non-empty path and namespace.');
    }

    $provenance = $manifest['provenance'] ?? null;

    if ($provenance !== null && ! is_array($provenance)) {
        throw new RuntimeException('Corpus provenance must be a JSON object.');
    }

    if (is_array($provenance)) {
        $repository = $provenance['repository'] ?? null;
        $revision = $provenance['revision'] ?? null;

        if ($repository !== null && (! is_string($repository) || trim($repository) === '')) {
            throw new RuntimeException('Corpus provenance repository must be a non-empty string.');
        }

        if ($revision !== null
            && (! is_string($revision) || preg_match('/\A[0-9a-f]{40}\z/iD', $revision) !== 1)) {
            throw new RuntimeException('Corpus provenance revision must be a full Git object ID.');
        }
    }

    /** @var array{
     *     schema: 1,
     *     name: non-empty-string,
     *     provenance?: array{repository?: string, revision?: string},
     *     source_roots: non-empty-list<array{
     *         path: string,
     *         namespace: string,
     *         group_by?: 'first-directory'|'single',
     *         group?: string
     *     }>,
     *     command_root?: array{path: string, namespace: string}
     * } $manifest
     */
    $result = (new CorpusAnalyser)->analyse($manifest, $root);
    $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
    $output = $options['output'] ?? null;

    if (is_string($output) && $output !== '') {
        if (file_put_contents($output, $json) === false) {
            throw new RuntimeException("Unable to write corpus report [{$output}].");
        }

        echo "Corpus report written to {$output}.\n";
    } else {
        echo $json;
    }

    $oracles = $result['oracles'] ?? null;
    $commands = $result['command_discovery'] ?? null;

    if (! is_array($oracles) || ! is_array($commands)) {
        throw new RuntimeException('Corpus report is missing oracle or command results.');
    }

    $precision = $oracles['precision'] ?? null;
    $anchoring = $oracles['anchoring'] ?? null;
    $recall = $oracles['literal_facade_recall'] ?? null;

    if (! is_array($precision) || ! is_array($anchoring) || ! is_array($recall)) {
        throw new RuntimeException('Corpus report contains invalid oracle results.');
    }

    $precisionMisses = $precision['misses'] ?? [];
    $anchoringCollisions = $anchoring['collision_locations'] ?? 0;
    $recallMisses = $recall['misses'] ?? [];
    $commandStatus = $commands['status'] ?? 'failed';

    exit($precisionMisses === []
        && $anchoringCollisions === 0
        && $recallMisses === []
        && $commandStatus !== 'failed' ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Corpus analysis failed: '.$exception->getMessage().PHP_EOL);
    exit(2);
}

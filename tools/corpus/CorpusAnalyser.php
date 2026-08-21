<?php

declare(strict_types=1);

namespace Tools\Corpus;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\Rules\UndeclaredDependenciesRule;
use Cluion\Moduark\Analysis\Source\SchemaMutation;
use Cluion\Moduark\Analysis\Source\SourceAnalysisCacheStore;
use Cluion\Moduark\Analysis\Source\SourceIndex;
use Cluion\Moduark\Analysis\Source\SourceIndexBuilder;
use Cluion\Moduark\Analysis\Source\TableAccess;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use Cluion\Moduark\Resources\ModuleResourceDiscoverer;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

/**
 * This internal harness deliberately uses token-based oracles that do not reuse
 * Moduark's AST visitors. It projects external sources into disposable Modules
 * so no corpus checkout needs to be edited for an adoption run.
 *
 * @phpstan-type SourceRoot array{
 *     path: string,
 *     namespace: string,
 *     group_by?: 'first-directory'|'single',
 *     group?: string
 * }
 * @phpstan-type CommandRoot array{path: string, namespace: string}
 * @phpstan-type CorpusManifest array{
 *     schema: 1,
 *     name: non-empty-string,
 *     provenance?: array{repository?: string, revision?: string},
 *     source_roots: non-empty-list<SourceRoot>,
 *     command_root?: CommandRoot
 * }
 * @phpstan-type FileMap array<string, array{absolute: string, relative: string}>
 * @phpstan-type OracleFinding array{
 *     file: string,
 *     line: int,
 *     operation: string,
 *     evidence: string
 * }
 * @phpstan-type PhpToken array{int, string, int}|string
 */
final class CorpusAnalyser
{
    /**
     * @param CorpusManifest $manifest
     * @return array<string, mixed>
     */
    public function analyse(array $manifest, string $root): array
    {
        $root = realpath($root) ?: '';

        if ($root === '' || ! is_dir($root)) {
            throw new RuntimeException('Corpus root must be an existing directory.');
        }

        $this->verifyRevision($manifest, $root);

        $temporaryPath = sys_get_temp_dir().'/moduark-corpus-'.bin2hex(random_bytes(8));

        if (! mkdir($temporaryPath, 0755, true) && ! is_dir($temporaryPath)) {
            throw new RuntimeException("Unable to create corpus projection [{$temporaryPath}].");
        }

        try {
            [$registry, $files, $sourceCounts] = $this->project(
                $manifest['source_roots'],
                $root,
                $temporaryPath,
            );
            $cache = new SourceAnalysisCacheStore($temporaryPath.'/cache/moduark-analysis.php');

            $coldStarted = hrtime(true);
            $coldIndex = (new SourceIndexBuilder($registry, $cache))->build();
            $coldFinished = hrtime(true);
            $warmIndex = (new SourceIndexBuilder($registry, $cache))->build();
            $warmFinished = hrtime(true);

            if ($this->indexDigest($coldIndex) !== $this->indexDigest($warmIndex)) {
                throw new RuntimeException('Cold and warm corpus indexes are not identical.');
            }

            $descriptors = array_map(
                static fn (string $module): ModuleDescriptor => new ModuleDescriptor($module, [], []),
                $registry->moduleClasses(),
            );
            $context = new AnalysisContext($registry, $descriptors, $warmIndex);
            $dependencyResult = (new UndeclaredDependenciesRule)->inspect(
                $context,
                RuleId::UndeclaredDependencies->defaultSeverity(),
            );
            $crossModuleReferences = array_values(array_filter(
                $warmIndex->references(),
                static fn ($reference): bool => $reference->source() !== $reference->target(),
            ));
            $dependencyPairs = [];

            foreach ($crossModuleReferences as $reference) {
                $dependencyPairs[$reference->source()."\0".$reference->target()] = true;
            }

            $precision = $this->precisionOracle($warmIndex, $files);
            $anchoring = $this->anchoringOracle($warmIndex, $files);
            $recall = $this->recallOracle($warmIndex, $files);
            $unresolved = $this->unresolvedEvidence($warmIndex, $files);
            $commands = isset($manifest['command_root'])
                ? $this->discoverCommands($root, $manifest['command_root'])
                : ['status' => 'not-configured', 'count' => 0, 'classes' => []];

            return [
                'schema' => 1,
                'corpus' => [
                    'name' => $manifest['name'],
                    'provenance' => $manifest['provenance'] ?? [],
                    'source_counts' => $sourceCounts,
                    'analysed_php_files' => count($files),
                    'synthetic_modules' => count($registry->all()),
                ],
                'analysis' => [
                    'symbols' => count($warmIndex->symbols()),
                    'class_references' => count($warmIndex->references()),
                    'class_references_by_source_root' => $this->referenceCountsBySourceRoot(
                        $warmIndex,
                        $files,
                        array_keys($sourceCounts),
                    ),
                    'cross_module_references' => count($crossModuleReferences),
                    'cross_module_pairs' => count($dependencyPairs),
                    'undeclared_dependency_violations' => count($dependencyResult->violations()),
                    'table_accesses' => count($warmIndex->tableAccesses()),
                    'schema_mutations' => count($warmIndex->schemaMutations()),
                    'foreign_keys' => count($warmIndex->foreignKeyReferences()),
                    'transaction_scopes' => count($warmIndex->transactionScopes()),
                    'unresolved_evidence' => $unresolved,
                    'table_inventory' => $this->tableInventory($warmIndex),
                ],
                'oracles' => [
                    'precision' => $precision,
                    'anchoring' => $anchoring,
                    'literal_facade_recall' => $recall,
                ],
                'command_discovery' => $commands,
                'performance_ms' => [
                    'cold' => $this->milliseconds($coldStarted, $coldFinished),
                    'warm' => $this->milliseconds($coldFinished, $warmFinished),
                ],
            ];
        } finally {
            $this->deleteDirectory($temporaryPath);
        }
    }

    /** @param CorpusManifest $manifest */
    private function verifyRevision(array $manifest, string $root): void
    {
        $expected = $manifest['provenance']['revision'] ?? null;

        if ($expected === null || $expected === '') {
            return;
        }

        $process = proc_open(
            ['git', '-C', $root, 'rev-parse', 'HEAD'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Unable to verify the pinned corpus revision.');
        }

        $current = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 || ! is_string($current)) {
            throw new RuntimeException('Unable to read corpus revision: '.trim((string) $error));
        }

        $current = trim($current);

        if (! hash_equals(strtolower($expected), strtolower($current))) {
            throw new RuntimeException(
                "Corpus revision [{$current}] does not match pinned revision [{$expected}].",
            );
        }
    }

    /**
     * @param non-empty-list<array{
     *     path: string,
     *     namespace: string,
     *     group_by?: 'first-directory'|'single',
     *     group?: string
     * }> $sourceRoots
     * @return array{ModuleRegistry, FileMap, array<string, int>}
     */
    private function project(array $sourceRoots, string $root, string $temporaryPath): array
    {
        $groups = [];
        $fileMap = [];
        $sourceCounts = [];
        $token = 'C'.bin2hex(random_bytes(6));

        foreach ($sourceRoots as $sourceIndex => $sourceRoot) {
            $relativeRoot = trim(str_replace('\\', '/', $sourceRoot['path']), '/');
            $absoluteRoot = $root.'/'.$relativeRoot;

            if (! is_dir($absoluteRoot)) {
                throw new RuntimeException("Corpus source root [{$relativeRoot}] does not exist.");
            }

            $sourceCounts[$relativeRoot] = 0;

            foreach ($this->phpFiles($absoluteRoot) as $file) {
                $relativeFile = substr($file, strlen($absoluteRoot) + 1);
                [$group, $insideGroup] = $this->group($sourceRoot, $relativeFile);
                $groupKey = sprintf('S%d%s', $sourceIndex + 1, $this->className($group));
                $groupRoot = $temporaryPath.'/Modules/'.$groupKey;
                $destination = $groupRoot.'/'.$insideGroup;
                $directory = dirname($destination);

                if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                    throw new RuntimeException("Unable to create corpus group [{$directory}].");
                }

                if (! copy($file, $destination)) {
                    throw new RuntimeException("Unable to project corpus file [{$file}].");
                }

                $groupNamespace = rtrim($sourceRoot['namespace'], '\\');

                if (($sourceRoot['group_by'] ?? 'single') === 'first-directory'
                    && str_contains(str_replace('\\', '/', $relativeFile), '/')) {
                    $groupNamespace .= '\\'.strtok(str_replace('\\', '/', $relativeFile), '/');
                }

                $groups[$groupKey] = [
                    'root' => $groupRoot,
                    'namespace' => $groupNamespace,
                    'class' => "Tools\\Corpus\\Generated\\{$token}\\{$groupKey}Module",
                ];
                $fileMap[$destination] = [
                    'absolute' => $file,
                    'relative' => $relativeRoot.'/'.$relativeFile,
                ];
                $sourceCounts[$relativeRoot]++;
            }
        }

        if ($fileMap === []) {
            throw new RuntimeException('Corpus source roots contain no PHP files.');
        }

        ksort($groups, SORT_STRING);
        $modules = [];

        foreach ($groups as $name => $group) {
            $moduleClass = $this->defineModule($group['class']);
            $modules[] = new DiscoveredModule(
                $name,
                $moduleClass,
                $group['root'].'/'.$name.'Module.php',
                $group['namespace'],
            );
        }

        ksort($fileMap, SORT_STRING);
        ksort($sourceCounts, SORT_STRING);

        return [new ModuleRegistry($modules), $fileMap, $sourceCounts];
    }

    /**
     * @param array{
     *     path: string,
     *     namespace: string,
     *     group_by?: 'first-directory'|'single',
     *     group?: string
     * } $sourceRoot
     * @return array{non-empty-string, non-empty-string}
     */
    private function group(array $sourceRoot, string $relativeFile): array
    {
        $normalized = str_replace('\\', '/', $relativeFile);

        if (($sourceRoot['group_by'] ?? 'single') !== 'first-directory'
            || ! str_contains($normalized, '/')) {
            $group = $sourceRoot['group'] ?? basename($sourceRoot['path']);

            if ($normalized === '') {
                throw new RuntimeException('Corpus source path must not be empty.');
            }

            return [$this->className($group), $normalized];
        }

        [$group, $insideGroup] = explode('/', $normalized, 2);

        if ($insideGroup === '') {
            throw new RuntimeException('Corpus group file path must not be empty.');
        }

        return [$this->className($group), $insideGroup];
    }

    /**
     * @return class-string<Module>
     */
    private function defineModule(string $class): string
    {
        $position = strrpos($class, '\\');

        if ($position === false) {
            throw new RuntimeException("Generated Module class [{$class}] has no namespace.");
        }

        $namespace = substr($class, 0, $position);
        $shortName = substr($class, $position + 1);
        eval("namespace {$namespace}; final class {$shortName} extends \\Cluion\\Moduark\\Module {}");

        if (! is_a($class, Module::class, true)) {
            throw new RuntimeException("Unable to define generated Module [{$class}].");
        }

        return $class;
    }

    /** @return non-empty-string */
    private function className(string $value): string
    {
        $parts = preg_split('/[^A-Za-z0-9]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $name = implode('', array_map(static fn (string $part): string => ucfirst($part), $parts));

        if ($name === '' || ctype_digit($name[0])) {
            $name = 'Group'.$name;
        }

        return $name;
    }

    /**
     * @param FileMap $files
     * @return array{checked: int, misses: list<OracleFinding>}
     */
    private function precisionOracle(SourceIndex $index, array $files): array
    {
        $checked = 0;
        $misses = [];

        foreach ([...$index->tableAccesses(), ...$index->schemaMutations()] as $evidence) {
            $table = $evidence->table();

            if ($table === null) {
                continue;
            }

            $checked++;
            $line = $this->sourceLine($files, $evidence->file(), $evidence->line());

            if (str_contains(strtolower($line), strtolower($table))) {
                continue;
            }

            $misses[] = $this->finding(
                $files,
                $evidence->file(),
                $evidence->line(),
                $evidence->operation(),
                $table,
            );
        }

        return ['checked' => $checked, 'misses' => $misses];
    }

    /**
     * @param FileMap $files
     * @return array{collision_locations: int, evidence_in_collisions: int, collisions: list<array{file: string, line: int, count: int}>}
     */
    private function anchoringOracle(SourceIndex $index, array $files): array
    {
        $locations = [];

        foreach ($index->tableAccesses() as $access) {
            $relative = $files[$access->file()]['relative'];
            $key = $relative."\0".$access->line();
            $locations[$key] = [
                'file' => $relative,
                'line' => $access->line(),
                'count' => ($locations[$key]['count'] ?? 0) + 1,
            ];
        }

        $collisions = array_values(array_filter(
            $locations,
            static fn (array $location): bool => $location['count'] > 1,
        ));
        usort(
            $collisions,
            static fn (array $left, array $right): int => [$left['file'], $left['line']]
                <=> [$right['file'], $right['line']],
        );

        return [
            'collision_locations' => count($collisions),
            'evidence_in_collisions' => array_sum(array_column($collisions, 'count')),
            'collisions' => $collisions,
        ];
    }

    /**
     * @param FileMap $files
     * @return array{
     *     expected: int,
     *     matched: int,
     *     by_operation: array<string, array{expected: int, matched: int}>,
     *     misses: list<OracleFinding>
     * }
     */
    private function recallOracle(SourceIndex $index, array $files): array
    {
        $actual = [];

        foreach ($index->tableAccesses() as $access) {
            if ($access->table() === null) {
                continue;
            }

            $actual[$this->evidenceKey(
                $files[$access->file()]['relative'],
                $access->line(),
                strtolower($access->operation()),
                $access->table(),
            )] = true;
        }

        foreach ($index->schemaMutations() as $mutation) {
            if ($mutation->table() === null) {
                continue;
            }

            $actual[$this->evidenceKey(
                $files[$mutation->file()]['relative'],
                $mutation->line(),
                strtolower($mutation->operation()).':'.$mutation->operand(),
                $mutation->table(),
            )] = true;
        }

        $expected = [];

        foreach ($files as $file) {
            $source = file_get_contents($file['absolute']);

            if ($source === false) {
                throw new RuntimeException("Unable to read corpus file [{$file['relative']}].");
            }

            foreach ($this->literalFacadeCalls($source) as $call) {
                $key = $this->evidenceKey(
                    $file['relative'],
                    $call['line'],
                    $call['operation'],
                    $call['table'],
                );
                $expected[$key] = [
                    'file' => $file['relative'],
                    'line' => $call['line'],
                    'operation' => $call['operation'],
                    'evidence' => $call['table'],
                ];
            }
        }

        ksort($expected, SORT_STRING);
        $misses = [];
        $byOperation = [];

        foreach ($expected as $key => $finding) {
            $operation = $finding['operation'];
            $byOperation[$operation] ??= ['expected' => 0, 'matched' => 0];
            $byOperation[$operation]['expected']++;

            if (! isset($actual[$key])) {
                $misses[] = $finding;
            } else {
                $byOperation[$operation]['matched']++;
            }
        }

        ksort($byOperation, SORT_STRING);

        return [
            'expected' => count($expected),
            'matched' => count($expected) - count($misses),
            'by_operation' => $byOperation,
            'misses' => $misses,
        ];
    }

    /**
     * @return list<array{line: int, operation: string, table: string}>
     */
    private function literalFacadeCalls(string $source): array
    {
        $tokens = array_values(array_filter(
            token_get_all($source),
            static fn (array|string $token): bool => is_string($token)
                || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG], true),
        ));
        $dbAliases = ['db' => true];
        $schemaAliases = ['schema' => true];

        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            if (! $this->tokenIs($tokens[$index], T_USE)) {
                continue;
            }

            $name = $tokens[$index + 1] ?? null;

            if (! is_array($name) || ! in_array($name[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                continue;
            }

            $normalized = strtolower(ltrim($name[1], '\\'));
            $separator = strrpos($name[1], '\\');
            $alias = strtolower($separator === false ? $name[1] : substr($name[1], $separator + 1));

            if ($this->tokenIs($tokens[$index + 2] ?? null, T_AS)
                && is_array($tokens[$index + 3] ?? null)) {
                $alias = strtolower($tokens[$index + 3][1]);
            }

            if ($normalized === 'illuminate\\support\\facades\\db') {
                $dbAliases[$alias] = true;
            } elseif ($normalized === 'illuminate\\support\\facades\\schema') {
                $schemaAliases[$alias] = true;
            }
        }

        $calls = [];

        for ($index = 0, $count = count($tokens); $index < $count - 3; $index++) {
            $class = $this->tokenText($tokens[$index]);

            if ($class === null || $this->tokenText($tokens[$index + 1]) !== '::') {
                continue;
            }

            $normalizedClass = strtolower(ltrim($class, '\\'));
            $facade = isset($dbAliases[$normalizedClass])
                || $normalizedClass === 'illuminate\\support\\facades\\db'
                ? 'db'
                : (isset($schemaAliases[$normalizedClass])
                    || $normalizedClass === 'illuminate\\support\\facades\\schema'
                    ? 'schema'
                    : null);

            if ($facade === null) {
                continue;
            }

            $method = strtolower($this->tokenText($tokens[$index + 2]) ?? '');

            if ($this->tokenText($tokens[$index + 3]) !== '(') {
                continue;
            }

            if ($facade === 'db' && $method === 'table') {
                $this->addTokenCall($calls, $tokens, $index + 3, 'db::table');
            } elseif ($facade === 'schema'
                && in_array($method, ['create', 'table', 'rename', 'drop', 'dropifexists'], true)) {
                $label = 'schema::'.($method === 'dropifexists' ? 'dropIfExists' : $method);

                if ($method === 'rename') {
                    $this->addTokenCall($calls, $tokens, $index + 3, 'schema::rename:from');
                    $this->addTokenCall($calls, $tokens, $index + 3, 'schema::rename:to', 1);
                } else {
                    $this->addTokenCall($calls, $tokens, $index + 3, strtolower($label).':table');
                }
            }

            if ($facade === 'db' && in_array($method, ['table', 'query', 'connection'], true)) {
                $this->addFluentTokenCalls($calls, $tokens, $index + 3, 'db');
            } elseif ($facade === 'schema' && $method === 'connection') {
                $this->addFluentTokenCalls($calls, $tokens, $index + 3, 'schema');
            }
        }

        $unique = [];

        foreach ($calls as $call) {
            $unique[$call['line']."\0".$call['operation']."\0".$call['table']] = $call;
        }

        return array_values($unique);
    }

    /**
     * @param list<PhpToken> $tokens
     * @param list<array{line: int, operation: string, table: string}> $calls
     */
    private function addFluentTokenCalls(array &$calls, array $tokens, int $openingParenthesis, string $facade): void
    {
        $depth = 0;
        $limit = count($tokens);

        for ($index = $openingParenthesis; $index < $limit; $index++) {
            $token = $tokens[$index];

            $tokenText = $this->tokenText($token);

            if ($tokenText === '(' || $tokenText === '[' || $tokenText === '{') {
                $depth++;
            } elseif ($tokenText === ')' || $tokenText === ']' || $tokenText === '}') {
                $depth--;
            } elseif ($tokenText === ';' && $depth <= 0) {
                break;
            }

            if ($tokenText !== '->' || $depth !== 0) {
                continue;
            }

            $method = strtolower($this->tokenText($tokens[$index + 1] ?? null) ?? '');

            if ($this->tokenText($tokens[$index + 2] ?? null) !== '(') {
                continue;
            }

            if ($facade === 'db'
                && in_array($method, ['table', 'from', 'join', 'joinwhere', 'leftjoin', 'leftjoinwhere', 'rightjoin', 'rightjoinwhere', 'crossjoin'], true)) {
                $operation = $method === 'table' ? 'db::connection()->table' : $method;
                $this->addTokenCall($calls, $tokens, $index + 2, $operation);
            } elseif ($facade === 'schema'
                && in_array($method, ['create', 'table', 'rename', 'drop', 'dropifexists'], true)) {
                $label = 'schema::connection()->'.($method === 'dropifexists' ? 'dropIfExists' : $method);

                if ($method === 'rename') {
                    $this->addTokenCall($calls, $tokens, $index + 2, strtolower($label).':from');
                    $this->addTokenCall($calls, $tokens, $index + 2, strtolower($label).':to', 1);
                } else {
                    $this->addTokenCall($calls, $tokens, $index + 2, strtolower($label).':table');
                }
            }
        }
    }

    /**
     * @param list<array{line: int, operation: string, table: string}> $calls
     * @param list<PhpToken> $tokens
     */
    private function addTokenCall(
        array &$calls,
        array $tokens,
        int $openingParenthesis,
        string $operation,
        int $argumentPosition = 0,
    ): void {
        $argument = $this->literalArgument($tokens, $openingParenthesis, $argumentPosition);

        if ($argument === null) {
            return;
        }

        $table = $this->literalTable($argument['value']);

        if ($table === null) {
            return;
        }

        $calls[] = [
            'line' => $argument['line'],
            'operation' => strtolower($operation),
            'table' => $table,
        ];
    }

    /**
     * @param list<PhpToken> $tokens
     * @return null|array{value: string, line: int}
     */
    private function literalArgument(array $tokens, int $openingParenthesis, int $position): ?array
    {
        $depth = 0;
        $argument = 0;

        for ($index = $openingParenthesis + 1, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];

            $tokenText = $this->tokenText($token);

            if ($tokenText === '(' || $tokenText === '[' || $tokenText === '{') {
                $depth++;
                continue;
            }

            if ($tokenText === ')' && $depth === 0) {
                return null;
            }

            if ($tokenText === ')' || $tokenText === ']' || $tokenText === '}') {
                $depth--;
                continue;
            }

            if ($tokenText === ',' && $depth === 0) {
                $argument++;
                continue;
            }

            if ($argument !== $position || ! is_array($token)
                || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }

            return [
                'value' => $this->decodeString($token[1]),
                'line' => $token[2],
            ];
        }

        return null;
    }

    private function decodeString(string $literal): string
    {
        $quote = $literal[0];
        $value = substr($literal, 1, -1);

        return $quote === "'"
            ? str_replace(["\\\\", "\\'"], ["\\", "'"], $value)
            : stripcslashes($value);
    }

    private function literalTable(string $literal): ?string
    {
        $literal = trim($literal);
        $tablePattern = '[A-Za-z_$][A-Za-z0-9_$-]*(?:\\.[A-Za-z_$][A-Za-z0-9_$-]*)*';

        if (preg_match('/\\A('.$tablePattern.')(?:\\s+(?:as\\s+)?[A-Za-z_$][A-Za-z0-9_$-]*)?\\z/iD', $literal, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @param FileMap $files
     * @return array{count: int, unique_locations: int, samples: list<OracleFinding>}
     */
    private function unresolvedEvidence(SourceIndex $index, array $files): array
    {
        $findings = [];

        foreach ($index->tableAccesses() as $access) {
            if ($access->table() === null) {
                $findings[] = $this->finding(
                    $files,
                    $access->file(),
                    $access->line(),
                    $access->operation(),
                    $access->evidence(),
                );
            }
        }

        foreach ($index->schemaMutations() as $mutation) {
            if ($mutation->table() === null) {
                $findings[] = $this->finding(
                    $files,
                    $mutation->file(),
                    $mutation->line(),
                    $mutation->label(),
                    $mutation->evidence(),
                );
            }
        }

        usort(
            $findings,
            static fn (array $left, array $right): int => [$left['file'], $left['line'], $left['operation']]
                <=> [$right['file'], $right['line'], $right['operation']],
        );
        $locations = [];

        foreach ($findings as $finding) {
            $locations[$finding['file']."\0".$finding['line']] = true;
        }

        return [
            'count' => count($findings),
            'unique_locations' => count($locations),
            'samples' => array_slice($findings, 0, 50),
        ];
    }

    /**
     * @param FileMap $files
     * @param list<string> $sourceRoots
     * @return array<string, int>
     */
    private function referenceCountsBySourceRoot(
        SourceIndex $index,
        array $files,
        array $sourceRoots,
    ): array {
        $counts = array_fill_keys($sourceRoots, 0);

        foreach ($index->references() as $reference) {
            $relative = $files[$reference->file()]['relative'];

            foreach ($sourceRoots as $sourceRoot) {
                if ($relative === $sourceRoot || str_starts_with($relative, $sourceRoot.'/')) {
                    $counts[$sourceRoot]++;
                    break;
                }
            }
        }

        ksort($counts, SORT_STRING);

        return $counts;
    }

    /**
     * @return array{
     *     accessed: list<string>,
     *     created: list<string>,
     *     accessed_without_create_evidence: list<string>
     * }
     */
    private function tableInventory(SourceIndex $index): array
    {
        $accessed = [];
        $created = [];

        foreach ($index->tableAccesses() as $access) {
            if ($access->table() !== null) {
                $accessed[strtolower($access->table())] = $access->table();
            }
        }

        foreach ($index->schemaMutations() as $mutation) {
            if ($mutation->table() === null) {
                continue;
            }

            if (strtolower($mutation->operation()) === 'schema::create'
                && $mutation->operand() === 'table') {
                $created[strtolower($mutation->table())] = $mutation->table();
            } elseif (strtolower($mutation->operation()) === 'schema::rename'
                && $mutation->operand() === 'to') {
                $created[strtolower($mutation->table())] = $mutation->table();
            }
        }

        ksort($accessed, SORT_STRING);
        ksort($created, SORT_STRING);

        return [
            'accessed' => array_values($accessed),
            'created' => array_values($created),
            'accessed_without_create_evidence' => array_values(array_diff_key($accessed, $created)),
        ];
    }

    /**
     * @param CommandRoot $commandRoot
     * @return array{status: string, count: int, classes: list<string>, error?: string}
     */
    private function discoverCommands(string $root, array $commandRoot): array
    {
        $relativePath = trim(str_replace('\\', '/', $commandRoot['path']), '/');
        $moduleRoot = $root.'/'.$relativePath;
        $namespace = rtrim($commandRoot['namespace'], '\\');
        $prefix = $namespace.'\\';
        $loader = static function (string $class) use ($prefix, $moduleRoot): void {
            if (! str_starts_with($class, $prefix)) {
                return;
            }

            $path = $moduleRoot.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

            if (is_file($path)) {
                require_once $path;
            }
        };
        spl_autoload_register($loader);

        try {
            $moduleClass = $this->commandModuleClass();
            $resources = (new ModuleResourceDiscoverer)->discover(
                new DiscoveredModule(
                    'Corpus',
                    $moduleClass,
                    $moduleRoot.'/CorpusModule.php',
                    $namespace,
                ),
                true,
            );

            return [
                'status' => 'passed',
                'count' => count($resources->commands()),
                'classes' => $resources->commands(),
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'count' => 0,
                'classes' => [],
                'error' => str_replace($root, '<corpus>', $exception->getMessage()),
            ];
        } finally {
            spl_autoload_unregister($loader);
        }
    }

    /** @return class-string<Module> */
    private function commandModuleClass(): string
    {
        $class = 'Tools\\Corpus\\Generated\\Command\\M'.bin2hex(random_bytes(6));

        return $this->defineModule($class);
    }

    private function indexDigest(SourceIndex $index): string
    {
        $data = [
            array_map(static fn ($value): array => $value->toArray(), $index->symbols()),
            array_map(static fn ($value): array => $value->toArray(), $index->references()),
            array_map(static fn ($value): array => $value->toArray(), $index->tableAccesses()),
            array_map(static fn ($value): array => $value->toArray(), $index->schemaMutations()),
            array_map(static fn ($value): array => $value->toArray(), $index->foreignKeyReferences()),
            array_map(static fn ($value): array => $value->toArray(), $index->transactionScopes()),
        ];

        return hash('sha256', serialize($data));
    }

    /** @return list<string> */
    private function phpFiles(string $path): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && ! $file->isLink() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * @param FileMap $files
     */
    private function sourceLine(array $files, string $file, int $line): string
    {
        $lines = file($files[$file]['absolute']);

        return is_array($lines) ? ($lines[$line - 1] ?? '') : '';
    }

    /**
     * @param FileMap $files
     * @return OracleFinding
     */
    private function finding(
        array $files,
        string $file,
        int $line,
        string $operation,
        string $evidence,
    ): array {
        return [
            'file' => $files[$file]['relative'],
            'line' => $line,
            'operation' => $operation,
            'evidence' => $evidence,
        ];
    }

    private function evidenceKey(string $file, int $line, string $operation, string $table): string
    {
        return strtolower($file)."\0".$line."\0".strtolower($operation)."\0".strtolower($table);
    }

    /** @param PhpToken|null $token */
    private function tokenIs(array|string|null $token, int $id): bool
    {
        return is_array($token) && $token[0] === $id;
    }

    /** @param PhpToken|null $token */
    private function tokenText(array|string|null $token): ?string
    {
        return is_array($token) ? $token[1] : (is_string($token) ? $token : null);
    }

    private function milliseconds(int $start, int $end): float
    {
        return round(($end - $start) / 1_000_000, 3);
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $entry */
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($path);
    }
}

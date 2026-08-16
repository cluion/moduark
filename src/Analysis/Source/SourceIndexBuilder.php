<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Source;

use Cluion\Moduark\Analysis\Source\Visitors\ClassReferenceCollector;
use Cluion\Moduark\Analysis\Source\Visitors\DatabaseTableAccessCollector;
use Cluion\Moduark\Analysis\Source\Visitors\SymbolCollector;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Exceptions\SourceAnalysisFailed;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use FilesystemIterator;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;
use UnexpectedValueException;

final readonly class SourceIndexBuilder
{
    public function __construct(
        private ModuleRegistry $registry,
        private ?SourceAnalysisCacheStore $cache = null,
    ) {
    }

    public function build(): SourceIndex
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $cached = $this->cache?->load();
        $analyses = [];
        $symbols = [];
        $candidates = [];
        $tableAccesses = [];

        foreach ($this->registry->all() as $module) {
            foreach ($this->phpFiles($module) as $file) {
                $source = file_get_contents($file);

                if ($source === false) {
                    throw SourceAnalysisFailed::unreadableFile($file);
                }

                $hash = hash('sha256', $source);
                $analysis = $cached?->match($file, $hash, $module->moduleClass())
                    ?? $this->analyze($parser, $module, $file, $source, $hash);
                $analyses[] = $analysis;

                array_push($symbols, ...$analysis->symbols());

                foreach ($analysis->references() as $reference) {
                    $candidates[] = [
                        'source' => $module->moduleClass(),
                        'symbol' => $reference['symbol'],
                        'file' => $file,
                        'line' => $reference['line'],
                    ];
                }

                foreach ($analysis->tableAccesses() as $access) {
                    $tableAccesses[] = new TableAccess(
                        $module->moduleClass(),
                        $access['table'],
                        $access['expression'],
                        $access['operation'],
                        $file,
                        $access['line'],
                    );
                }
            }
        }

        $symbolIndex = new SourceIndex($symbols, []);
        $references = [];

        foreach ($candidates as $candidate) {
            $target = $symbolIndex->symbol($candidate['symbol']);

            if ($target === null) {
                continue;
            }

            $references[] = new SourceReference(
                $candidate['source'],
                $target->owner(),
                $target->name(),
                $candidate['file'],
                $candidate['line'],
            );
        }

        $index = new SourceIndex($symbols, $references, $tableAccesses);

        if ($this->cache !== null) {
            try {
                $this->cache->write(new SourceAnalysisCache($analyses));
            } catch (Throwable) {
                // The cache is an optimization; a fresh complete index remains authoritative.
            }
        }

        return $index;
    }

    private function analyze(
        Parser $parser,
        DiscoveredModule $module,
        string $file,
        string $source,
        string $hash,
    ): SourceFileAnalysis {
        try {
            $statements = $parser->parse($source) ?? [];
            $symbolCollector = new SymbolCollector($module->moduleClass(), $file);
            $referenceCollector = new ClassReferenceCollector;
            $tableAccessCollector = new DatabaseTableAccessCollector;
            $traverser = new NodeTraverser(
                new NameResolver,
                $symbolCollector,
                $referenceCollector,
                $tableAccessCollector,
            );
            $traverser->traverse($statements);
        } catch (Error $error) {
            throw SourceAnalysisFailed::invalidSyntax(
                $file,
                $error->getStartLine(),
                $error->getRawMessage(),
            );
        }

        return new SourceFileAnalysis(
            $hash,
            $module->moduleClass(),
            $file,
            $symbolCollector->symbols(),
            $referenceCollector->references(),
            $tableAccessCollector->accesses(),
        );
    }

    /**
     * @return list<string>
     */
    private function phpFiles(DiscoveredModule $module): array
    {
        $path = dirname($module->path());
        $files = [];

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->isLink() || $file->getExtension() !== 'php') {
                    continue;
                }

                $files[] = $file->getPathname();
            }
        } catch (UnexpectedValueException $exception) {
            throw SourceAnalysisFailed::scanFailed($path, $exception->getMessage());
        }

        sort($files, SORT_STRING);

        return $files;
    }
}

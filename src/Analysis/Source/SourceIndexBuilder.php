<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Source;

use Cluion\Moduark\Analysis\Source\Visitors\ClassReferenceCollector;
use Cluion\Moduark\Analysis\Source\Visitors\SymbolCollector;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Exceptions\SourceAnalysisFailed;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use FilesystemIterator;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;

final readonly class SourceIndexBuilder
{
    public function __construct(private ModuleRegistry $registry)
    {
    }

    public function build(): SourceIndex
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $symbols = [];
        $candidates = [];

        foreach ($this->registry->all() as $module) {
            foreach ($this->phpFiles($module) as $file) {
                $source = file_get_contents($file);

                if ($source === false) {
                    throw SourceAnalysisFailed::unreadableFile($file);
                }

                try {
                    $statements = $parser->parse($source) ?? [];
                    $symbolCollector = new SymbolCollector($module->moduleClass(), $file);
                    $referenceCollector = new ClassReferenceCollector;
                    $traverser = new NodeTraverser(
                        new NameResolver,
                        $symbolCollector,
                        $referenceCollector,
                    );
                    $traverser->traverse($statements);
                } catch (Error $error) {
                    throw SourceAnalysisFailed::invalidSyntax(
                        $file,
                        $error->getStartLine(),
                        $error->getRawMessage(),
                    );
                }

                array_push($symbols, ...$symbolCollector->symbols());

                foreach ($referenceCollector->references() as $reference) {
                    $candidates[] = [
                        'source' => $module->moduleClass(),
                        'symbol' => $reference['symbol'],
                        'file' => $file,
                        'line' => $reference['line'],
                    ];
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

        return new SourceIndex($symbols, $references);
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

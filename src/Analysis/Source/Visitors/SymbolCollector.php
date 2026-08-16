<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Source\Visitors;

use Cluion\Moduark\Analysis\Source\SourceSymbol;
use Cluion\Moduark\Module;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeVisitorAbstract;

final class SymbolCollector extends NodeVisitorAbstract
{
    /** @var list<SourceSymbol> */
    private array $symbols = [];

    /**
     * @param class-string<Module> $owner
     */
    public function __construct(
        private readonly string $owner,
        private readonly string $file,
    ) {
    }

    public function enterNode(Node $node): null
    {
        if (! $node instanceof ClassLike || $node->namespacedName === null) {
            return null;
        }

        $this->symbols[] = new SourceSymbol(
            $node->namespacedName->toString(),
            $this->owner,
            $this->file,
            $node->getStartLine(),
            $node instanceof Class_ && $node->extends !== null
                ? $node->extends->toString()
                : null,
        );

        return null;
    }

    /**
     * @return list<SourceSymbol>
     */
    public function symbols(): array
    {
        return $this->symbols;
    }
}

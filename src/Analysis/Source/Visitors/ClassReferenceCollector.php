<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Source\Visitors;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt;
use PhpParser\Node\UnionType;
use PhpParser\Node\IntersectionType;
use PhpParser\NodeVisitorAbstract;

final class ClassReferenceCollector extends NodeVisitorAbstract
{
    /** @var list<array{symbol: string, line: int}> */
    private array $references = [];

    public function enterNode(Node $node): null
    {
        if ($node instanceof Stmt\Class_) {
            $this->addName($node->extends);

            foreach ($node->implements as $interface) {
                $this->addName($interface);
            }
        } elseif ($node instanceof Stmt\Interface_) {
            foreach ($node->extends as $interface) {
                $this->addName($interface);
            }
        } elseif ($node instanceof Stmt\Enum_) {
            foreach ($node->implements as $interface) {
                $this->addName($interface);
            }
        } elseif ($node instanceof Stmt\TraitUse) {
            foreach ($node->traits as $trait) {
                $this->addName($trait);
            }
        } elseif ($node instanceof Stmt\Catch_) {
            foreach ($node->types as $type) {
                $this->addName($type);
            }
        } elseif ($node instanceof Node\Param
            || $node instanceof Stmt\Property
            || $node instanceof Stmt\ClassConst) {
            $this->addType($node->type);
        } elseif ($node instanceof FunctionLike) {
            $this->addType($node->getReturnType());
        } elseif ($node instanceof Attribute) {
            $this->addName($node->name);
        } elseif ($node instanceof Expr\StaticCall
            || $node instanceof Expr\StaticPropertyFetch
            || $node instanceof Expr\ClassConstFetch
            || $node instanceof Expr\New_
            || $node instanceof Expr\Instanceof_) {
            if ($node->class instanceof Name) {
                $this->addName($node->class);
            }
        }

        return null;
    }

    /**
     * @return list<array{symbol: string, line: int}>
     */
    public function references(): array
    {
        return $this->references;
    }

    private function addType(?Node $type): void
    {
        if ($type instanceof Name) {
            $this->addName($type);

            return;
        }

        if ($type instanceof NullableType) {
            $this->addType($type->type);

            return;
        }

        if ($type instanceof UnionType || $type instanceof IntersectionType) {
            foreach ($type->types as $member) {
                $this->addType($member);
            }
        }
    }

    private function addName(?Name $name): void
    {
        if ($name === null || $name->isSpecialClassName()) {
            return;
        }

        $this->references[] = [
            'symbol' => $name->toString(),
            'line' => $name->getStartLine(),
        ];
    }
}

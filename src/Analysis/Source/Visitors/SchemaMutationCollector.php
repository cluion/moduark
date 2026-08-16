<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Source\Visitors;

use Cluion\Moduark\Persistence\TableName;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\VariadicPlaceholder;
use PhpParser\NodeVisitorAbstract;

final class SchemaMutationCollector extends NodeVisitorAbstract
{
    private const SCHEMA_FACADE = 'illuminate\\support\\facades\\schema';

    /** @var array<string, array<string, int>> */
    private const OPERANDS = [
        'create' => ['table' => 0],
        'table' => ['table' => 0],
        'rename' => ['from' => 0, 'to' => 1],
        'drop' => ['table' => 0],
        'dropifexists' => ['table' => 0],
    ];

    /** @var array<string, string> */
    private const METHOD_LABELS = [
        'create' => 'create',
        'table' => 'table',
        'rename' => 'rename',
        'drop' => 'drop',
        'dropifexists' => 'dropIfExists',
    ];

    /** @var list<array{table: ?string, expression: ?string, operation: string, operand: string, line: int}> */
    private array $mutations = [];

    public function enterNode(Node $node): null
    {
        if ($node instanceof Expr\StaticCall) {
            $class = $this->className($node);
            $method = $this->methodName($node->name);

            if ($class === self::SCHEMA_FACADE
                && $method !== null
                && isset(self::OPERANDS[$method])) {
                $this->collect(
                    $node->args,
                    $method,
                    'Schema::'.self::METHOD_LABELS[$method],
                    $node->getStartLine(),
                );
            }
        }

        return null;
    }

    public function leaveNode(Node $node): null
    {
        if (! $node instanceof Expr\MethodCall || ! $this->isSchemaConnection($node->var)) {
            return null;
        }

        $method = $this->methodName($node->name);

        if ($method !== null && isset(self::OPERANDS[$method])) {
            $this->collect(
                $node->args,
                $method,
                'Schema::connection()->'.self::METHOD_LABELS[$method],
                $node->getStartLine(),
            );
        }

        return null;
    }

    /**
     * @return list<array{table: ?string, expression: ?string, operation: string, operand: string, line: int}>
     */
    public function mutations(): array
    {
        return $this->mutations;
    }

    /**
     * @param array<Arg|VariadicPlaceholder> $arguments
     */
    private function collect(array $arguments, string $method, string $operation, int $line): void
    {
        foreach (self::OPERANDS[$method] as $operand => $position) {
            $argument = $arguments[$position] ?? null;
            $literal = $argument instanceof Arg && $argument->value instanceof String_
                ? $argument->value->value
                : null;
            $table = $literal !== null && TableName::valid($literal) ? $literal : null;

            $this->mutations[] = [
                'table' => $table,
                'expression' => $table === null && $literal !== null && trim($literal) !== ''
                    ? $literal
                    : null,
                'operation' => $operation,
                'operand' => $operand,
                'line' => $line,
            ];
        }
    }

    private function className(Expr\StaticCall $call): ?string
    {
        return $call->class instanceof Name
            ? strtolower(ltrim($call->class->toString(), '\\'))
            : null;
    }

    private function isSchemaConnection(Expr $expression): bool
    {
        return $expression instanceof Expr\StaticCall
            && $this->className($expression) === self::SCHEMA_FACADE
            && $this->methodName($expression->name) === 'connection';
    }

    private function methodName(Node $name): ?string
    {
        return $name instanceof Identifier ? strtolower($name->toString()) : null;
    }
}

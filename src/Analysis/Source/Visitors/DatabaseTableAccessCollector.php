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

final class DatabaseTableAccessCollector extends NodeVisitorAbstract
{
    private const DB_FACADE = 'illuminate\\support\\facades\\db';

    private const SCHEMA_FACADE = 'illuminate\\support\\facades\\schema';

    /** @var list<string> */
    private const QUERY_TABLE_METHODS = [
        'from',
        'join',
        'joinwhere',
        'leftjoin',
        'leftjoinwhere',
        'rightjoin',
        'rightjoinwhere',
        'crossjoin',
    ];

    /** @var list<array{table: ?string, expression: ?string, operation: string, line: int}> */
    private array $accesses = [];

    public function enterNode(Node $node): null
    {
        if ($node instanceof Expr\StaticCall) {
            $this->collectStaticCall($node);
        }

        return null;
    }

    public function leaveNode(Node $node): null
    {
        if ($node instanceof Expr\MethodCall) {
            $this->collectMethodCall($node);
        }

        return null;
    }

    /**
     * @return list<array{table: ?string, expression: ?string, operation: string, line: int}>
     */
    public function accesses(): array
    {
        return $this->accesses;
    }

    private function collectStaticCall(Expr\StaticCall $call): void
    {
        $class = $this->className($call);
        $method = $this->methodName($call->name);

        if ($class === self::DB_FACADE && $method === 'table') {
            $this->add($call->args[0] ?? null, 'DB::table', $call->getStartLine());
        } elseif ($class === self::SCHEMA_FACADE && $method === 'table') {
            $this->add($call->args[0] ?? null, 'Schema::table', $call->getStartLine());
        }
    }

    private function collectMethodCall(Expr\MethodCall $call): void
    {
        $method = $this->methodName($call->name);

        if ($method === 'table' && $this->isFacadeConnection($call->var, self::DB_FACADE)) {
            $this->add($call->args[0] ?? null, 'DB::connection()->table', $call->getStartLine());

            return;
        }

        if ($method === 'table' && $this->isFacadeConnection($call->var, self::SCHEMA_FACADE)) {
            $this->add($call->args[0] ?? null, 'Schema::connection()->table', $call->getStartLine());

            return;
        }

        if ($method === null
            || ! in_array($method, self::QUERY_TABLE_METHODS, true)
            || ! $this->isQueryBuilderExpression($call->var)) {
            return;
        }

        $this->add($call->args[0] ?? null, $method, $call->getStartLine());
    }

    private function isQueryBuilderExpression(Expr $expression): bool
    {
        if ($expression instanceof Expr\StaticCall) {
            return $this->className($expression) === self::DB_FACADE
                && in_array($this->methodName($expression->name), ['query', 'table'], true);
        }

        if (! $expression instanceof Expr\MethodCall) {
            return false;
        }

        $method = $this->methodName($expression->name);

        if (in_array($method, ['query', 'table'], true)
            && $this->isFacadeConnection($expression->var, self::DB_FACADE)) {
            return true;
        }

        return $this->isQueryBuilderExpression($expression->var);
    }

    private function add(Arg|VariadicPlaceholder|null $argument, string $operation, int $line): void
    {
        $literal = $argument instanceof Arg && $argument->value instanceof String_
            ? $argument->value->value
            : null;
        $table = $literal === null ? null : $this->tableFromLiteral($literal);

        $this->accesses[] = [
            'table' => $table,
            'expression' => $table === null && $literal !== null && trim($literal) !== ''
                ? $literal
                : null,
            'operation' => $operation,
            'line' => $line,
        ];
    }

    private function tableFromLiteral(string $literal): ?string
    {
        if (TableName::valid($literal)) {
            return $literal;
        }

        $tablePattern = '[A-Za-z_$][A-Za-z0-9_$-]*(?:\\.[A-Za-z_$][A-Za-z0-9_$-]*)*';
        $aliasPattern = '[A-Za-z_$][A-Za-z0-9_$-]*';

        if (preg_match(
            '/\\A('.$tablePattern.')\\s+(?:as\\s+)?'.$aliasPattern.'\\z/iD',
            $literal,
            $matches,
        ) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function className(Expr\StaticCall $call): ?string
    {
        return $call->class instanceof Name
            ? strtolower(ltrim($call->class->toString(), '\\'))
            : null;
    }

    private function isFacadeConnection(Expr $expression, string $facade): bool
    {
        return $expression instanceof Expr\StaticCall
            && $this->className($expression) === $facade
            && $this->methodName($expression->name) === 'connection';
    }

    private function methodName(Node $name): ?string
    {
        return $name instanceof Identifier ? strtolower($name->toString()) : null;
    }
}

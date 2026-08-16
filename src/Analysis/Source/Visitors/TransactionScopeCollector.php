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

final class TransactionScopeCollector extends NodeVisitorAbstract
{
    private const DB_FACADE = 'illuminate\\support\\facades\\db';

    /** @var array<string, string> */
    private const QUERY_WRITE_METHODS = [
        'insert' => 'insert',
        'insertorignore' => 'insertOrIgnore',
        'insertgetid' => 'insertGetId',
        'insertusing' => 'insertUsing',
        'insertorignoreusing' => 'insertOrIgnoreUsing',
        'update' => 'update',
        'updatefrom' => 'updateFrom',
        'updateorinsert' => 'updateOrInsert',
        'upsert' => 'upsert',
        'increment' => 'increment',
        'incrementeach' => 'incrementEach',
        'decrement' => 'decrement',
        'decrementeach' => 'decrementEach',
        'delete' => 'delete',
        'truncate' => 'truncate',
    ];

    /** @var array<string, string> */
    private const RAW_WRITE_METHODS = [
        'insert' => 'insert',
        'update' => 'update',
        'delete' => 'delete',
        'affectingstatement' => 'affectingStatement',
    ];

    /** @var list<int> */
    private array $closureStack = [];

    /**
     * @var array<int, list<array{
     *     table: ?string,
     *     expression: ?string,
     *     operation: string,
     *     line: int
     * }>>
     */
    private array $candidates = [];

    /**
     * @var list<array{
     *     operation: string,
     *     writes: list<array{table: ?string, expression: ?string, operation: string, line: int}>,
     *     line: int
     * }>
     */
    private array $scopes = [];

    public function enterNode(Node $node): null
    {
        if ($node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) {
            $this->closureStack[] = spl_object_id($node);
        }

        return null;
    }

    public function leaveNode(Node $node): null
    {
        if ($node instanceof Expr\MethodCall) {
            $this->collectMethodWrite($node);
            $this->bindConnectionTransaction($node);
        } elseif ($node instanceof Expr\StaticCall) {
            $this->collectStaticWrite($node);
            $this->bindStaticTransaction($node);
        }

        if ($node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) {
            array_pop($this->closureStack);
        }

        return null;
    }

    /**
     * @return list<array{
     *     operation: string,
     *     writes: list<array{table: ?string, expression: ?string, operation: string, line: int}>,
     *     line: int
     * }>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    private function collectMethodWrite(Expr\MethodCall $call): void
    {
        $method = $this->methodName($call->name);

        if ($method !== null && isset(self::QUERY_WRITE_METHODS[$method])) {
            $table = $this->queryBuilderTableEvidence($call->var);

            if ($table !== null) {
                $this->addCandidate(
                    $table,
                    'QueryBuilder::'.self::QUERY_WRITE_METHODS[$method],
                    $call->getStartLine(),
                );

                return;
            }
        }

        if ($method === null
            || ! isset(self::RAW_WRITE_METHODS[$method])
            || ! $this->isFacadeConnection($call->var)) {
            return;
        }

        $operation = 'DB::connection()->'.self::RAW_WRITE_METHODS[$method];
        $this->addCandidate(
            ['table' => null, 'expression' => $operation.'(sql:*)'],
            $operation,
            $call->getStartLine(),
        );
    }

    private function collectStaticWrite(Expr\StaticCall $call): void
    {
        $method = $this->methodName($call->name);

        if ($this->className($call) !== self::DB_FACADE
            || $method === null
            || ! isset(self::RAW_WRITE_METHODS[$method])) {
            return;
        }

        $operation = 'DB::'.self::RAW_WRITE_METHODS[$method];
        $this->addCandidate(
            ['table' => null, 'expression' => $operation.'(sql:*)'],
            $operation,
            $call->getStartLine(),
        );
    }

    /** @param array{table: ?string, expression: ?string} $table */
    private function addCandidate(array $table, string $operation, int $line): void
    {
        $closure = end($this->closureStack);

        if (! is_int($closure)) {
            return;
        }

        $this->candidates[$closure][] = [
            'table' => $table['table'],
            'expression' => $table['expression'],
            'operation' => $operation,
            'line' => $line,
        ];
    }

    private function bindStaticTransaction(Expr\StaticCall $call): void
    {
        if ($this->className($call) !== self::DB_FACADE
            || $this->methodName($call->name) !== 'transaction') {
            return;
        }

        $this->bindCallback($call->args, 'DB::transaction', $call->getStartLine());
    }

    private function bindConnectionTransaction(Expr\MethodCall $call): void
    {
        if ($this->methodName($call->name) !== 'transaction'
            || ! $this->isFacadeConnection($call->var)) {
            return;
        }

        $this->bindCallback(
            $call->args,
            'DB::connection()->transaction',
            $call->getStartLine(),
        );
    }

    /** @param array<Arg|VariadicPlaceholder> $arguments */
    private function bindCallback(array $arguments, string $operation, int $line): void
    {
        $callback = $this->argument($arguments, 0, 'callback')?->value;

        if (! $callback instanceof Expr\Closure && ! $callback instanceof Expr\ArrowFunction) {
            return;
        }

        $closure = spl_object_id($callback);
        $writes = $this->candidates[$closure] ?? [];
        unset($this->candidates[$closure]);

        if ($writes === []) {
            return;
        }

        $this->scopes[] = [
            'operation' => $operation,
            'writes' => $writes,
            'line' => $line,
        ];
    }

    /** @return array{table: ?string, expression: ?string}|null */
    private function queryBuilderTableEvidence(Expr $expression): ?array
    {
        $from = null;

        while ($expression instanceof Expr\MethodCall) {
            $method = $this->methodName($expression->name);

            if ($method === 'from' && $from === null) {
                $from = $this->argument($expression->args, 0, 'table');
            }

            if ($method === 'table' && $this->isFacadeConnection($expression->var)) {
                return $from !== null
                    ? $this->tableEvidence($from, 'DB::connection()->query()->from(table:*)')
                    : $this->tableEvidence(
                        $this->argument($expression->args, 0, 'table'),
                        'DB::connection()->table(table:*)',
                    );
            }

            if ($method === 'query' && $this->isFacadeConnection($expression->var)) {
                return $this->tableEvidence(
                    $from,
                    'DB::connection()->query()->from(table:*)',
                );
            }

            $expression = $expression->var;
        }

        if (! $expression instanceof Expr\StaticCall
            || $this->className($expression) !== self::DB_FACADE) {
            return null;
        }

        $method = $this->methodName($expression->name);

        if ($method === 'table') {
            return $from !== null
                ? $this->tableEvidence($from, 'DB::query()->from(table:*)')
                : $this->tableEvidence(
                    $this->argument($expression->args, 0, 'table'),
                    'DB::table(table:*)',
                );
        }

        return $method === 'query'
            ? $this->tableEvidence($from, 'DB::query()->from(table:*)')
            : null;
    }

    /** @return array{table: ?string, expression: ?string} */
    private function tableEvidence(?Arg $argument, string $fallback): array
    {
        $literal = $argument?->value instanceof String_
            ? $argument->value->value
            : null;
        $table = $literal === null ? null : $this->tableFromLiteral($literal);

        return [
            'table' => $table,
            'expression' => $table !== null
                ? null
                : ($literal !== null && trim($literal) !== '' ? $literal : $fallback),
        ];
    }

    private function tableFromLiteral(string $literal): ?string
    {
        if (TableName::valid($literal)) {
            return $literal;
        }

        $tablePattern = '[A-Za-z_$][A-Za-z0-9_$-]*(?:\\.[A-Za-z_$][A-Za-z0-9_$-]*)*';
        $aliasPattern = '[A-Za-z_$][A-Za-z0-9_$-]*';

        return preg_match(
            '/\\A('.$tablePattern.')\\s+(?:as\\s+)?'.$aliasPattern.'\\z/iD',
            $literal,
            $matches,
        ) === 1 ? $matches[1] : null;
    }

    /**
     * @param array<Arg|VariadicPlaceholder> $arguments
     */
    private function argument(array $arguments, int $position, string $name): ?Arg
    {
        foreach ($arguments as $argument) {
            if ($argument instanceof Arg
                && $argument->name instanceof Identifier
                && strtolower($argument->name->toString()) === $name) {
                return $argument;
            }
        }

        $argument = $arguments[$position] ?? null;

        return $argument instanceof Arg ? $argument : null;
    }

    private function className(Expr\StaticCall $call): ?string
    {
        return $call->class instanceof Name
            ? strtolower(ltrim($call->class->toString(), '\\'))
            : null;
    }

    private function isFacadeConnection(Expr $expression): bool
    {
        return $expression instanceof Expr\StaticCall
            && $this->className($expression) === self::DB_FACADE
            && $this->methodName($expression->name) === 'connection';
    }

    private function methodName(Node $name): ?string
    {
        return $name instanceof Identifier ? strtolower($name->toString()) : null;
    }
}

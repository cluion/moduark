<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Source\Visitors;

use Cluion\Moduark\Persistence\TableName;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\VariadicPlaceholder;
use PhpParser\NodeVisitorAbstract;

final class ForeignKeyReferenceCollector extends NodeVisitorAbstract
{
    private const SCHEMA_FACADE = 'illuminate\\support\\facades\\schema';

    /** @var array<string, string> */
    private const CONSTRAINED_ROOTS = [
        'foreignid' => 'foreignId',
        'foreignuuid' => 'foreignUuid',
        'foreignulid' => 'foreignUlid',
        'foreignidfor' => 'foreignIdFor',
        'foreignuuidfor' => 'foreignUuidFor',
        'foreignulidfor' => 'foreignUlidFor',
    ];

    /** @var list<int> */
    private array $closureStack = [];

    /**
     * @var array<int, list<array{
     *     variable: string,
     *     to_table: ?string,
     *     to_expression: ?string,
     *     operation: string,
     *     line: int
     * }>>
     */
    private array $candidates = [];

    /**
     * @var list<array{
     *     from_table: ?string,
     *     from_expression: ?string,
     *     to_table: ?string,
     *     to_expression: ?string,
     *     operation: string,
     *     line: int
     * }>
     */
    private array $references = [];

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
            $this->collectCandidate($node);
            $this->bindConnectionCallback($node);
        } elseif ($node instanceof Expr\StaticCall) {
            $this->bindStaticCallback($node);
        }

        if ($node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) {
            array_pop($this->closureStack);
        }

        return null;
    }

    /**
     * @return list<array{
     *     from_table: ?string,
     *     from_expression: ?string,
     *     to_table: ?string,
     *     to_expression: ?string,
     *     operation: string,
     *     line: int
     * }>
     */
    public function references(): array
    {
        return $this->references;
    }

    private function collectCandidate(Expr\MethodCall $call): void
    {
        $closure = end($this->closureStack);

        if (! is_int($closure)) {
            return;
        }

        $method = $this->methodName($call->name);

        if ($method === 'on') {
            $root = $this->rootCall($call->var, ['foreign']);

            if ($root !== null) {
                $this->addCandidate(
                    $closure,
                    $root,
                    $this->tableEvidence($this->argument($call->args, 0, 'table')),
                    'Blueprint::foreign()->on',
                    $call->getStartLine(),
                );
            }

            return;
        }

        if ($method !== 'constrained') {
            return;
        }

        $root = $this->rootCall($call->var, array_keys(self::CONSTRAINED_ROOTS));

        if ($root === null) {
            return;
        }

        $rootMethod = $this->methodName($root->name);

        if ($rootMethod === null) {
            return;
        }

        $this->addCandidate(
            $closure,
            $root,
            $this->constrainedTableEvidence($call, $root, $rootMethod),
            'Blueprint::'.self::CONSTRAINED_ROOTS[$rootMethod].'()->constrained',
            $call->getStartLine(),
        );
    }

    /**
     * @param array{table: ?string, expression: ?string} $target
     */
    private function addCandidate(
        int $closure,
        Expr\MethodCall $root,
        array $target,
        string $operation,
        int $line,
    ): void {
        $variable = $root->var;

        if (! $variable instanceof Expr\Variable || ! is_string($variable->name)) {
            return;
        }

        $this->candidates[$closure][] = [
            'variable' => $variable->name,
            'to_table' => $target['table'],
            'to_expression' => $target['expression'],
            'operation' => $operation,
            'line' => $line,
        ];
    }

    private function bindStaticCallback(Expr\StaticCall $call): void
    {
        $method = $this->methodName($call->name);

        if ($this->className($call) !== self::SCHEMA_FACADE
            || ! in_array($method, ['create', 'table'], true)) {
            return;
        }

        $this->bindCallback($call->args);
    }

    private function bindConnectionCallback(Expr\MethodCall $call): void
    {
        $method = $this->methodName($call->name);

        if (! in_array($method, ['create', 'table'], true)
            || ! $this->isSchemaConnection($call->var)) {
            return;
        }

        $this->bindCallback($call->args);
    }

    /**
     * @param array<Arg|VariadicPlaceholder> $arguments
     */
    private function bindCallback(array $arguments): void
    {
        $callbackArgument = $this->argument($arguments, 1, 'callback');
        $callback = $callbackArgument?->value;

        if (! $callback instanceof Expr\Closure && ! $callback instanceof Expr\ArrowFunction) {
            return;
        }

        $parameter = $callback->params[0]->var ?? null;

        if (! $parameter instanceof Expr\Variable || ! is_string($parameter->name)) {
            return;
        }

        $from = $this->tableEvidence($this->argument($arguments, 0, 'table'));
        $closure = spl_object_id($callback);

        foreach ($this->candidates[$closure] ?? [] as $candidate) {
            if ($candidate['variable'] !== $parameter->name) {
                continue;
            }

            $this->references[] = [
                'from_table' => $from['table'],
                'from_expression' => $from['expression'],
                'to_table' => $candidate['to_table'],
                'to_expression' => $candidate['to_expression'],
                'operation' => $candidate['operation'],
                'line' => $candidate['line'],
            ];
        }

        unset($this->candidates[$closure]);
    }

    /**
     * @return array{table: ?string, expression: ?string}
     */
    private function constrainedTableEvidence(
        Expr\MethodCall $call,
        Expr\MethodCall $root,
        string $rootMethod,
    ): array {
        $tableArgument = $this->argument($call->args, 0, 'table');

        if ($tableArgument !== null && ! $this->isNull($tableArgument->value)) {
            return $this->tableEvidence($tableArgument);
        }

        if (in_array($rootMethod, ['foreignidfor', 'foreignuuidfor', 'foreignulidfor'], true)) {
            return [
                'table' => null,
                'expression' => $this->modelEvidence(
                    $this->argument($root->args, 0, 'model'),
                ),
            ];
        }

        $foreignColumn = $this->stringLiteral(
            $this->argument($root->args, 0, 'column'),
        );
        $referencedColumnArgument = $this->argument($call->args, 1, 'column');
        $referencedColumn = $referencedColumnArgument === null
            || $this->isNull($referencedColumnArgument->value)
                ? 'id'
                : $this->stringLiteral($referencedColumnArgument);

        if ($foreignColumn === null || $referencedColumn === null) {
            return ['table' => null, 'expression' => null];
        }

        $table = Str::plural(Str::beforeLast(
            $foreignColumn,
            '_'.$referencedColumn,
        ));

        return TableName::valid($table)
            ? ['table' => $table, 'expression' => null]
            : ['table' => null, 'expression' => $foreignColumn];
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

    /**
     * @param list<string> $methods
     */
    private function rootCall(Expr $expression, array $methods): ?Expr\MethodCall
    {
        while ($expression instanceof Expr\MethodCall) {
            $method = $this->methodName($expression->name);

            if ($method !== null && in_array($method, $methods, true)) {
                return $expression;
            }

            $expression = $expression->var;
        }

        return null;
    }

    /**
     * @return array{table: ?string, expression: ?string}
     */
    private function tableEvidence(?Arg $argument): array
    {
        $literal = $this->stringLiteral($argument);
        $table = $literal !== null && TableName::valid($literal) ? $literal : null;

        return [
            'table' => $table,
            'expression' => $table === null && $literal !== null && trim($literal) !== ''
                ? $literal
                : null,
        ];
    }

    private function stringLiteral(?Arg $argument): ?string
    {
        return $argument?->value instanceof String_
            ? $argument->value->value
            : null;
    }

    private function modelEvidence(?Arg $argument): ?string
    {
        $expression = $argument?->value;

        if (! $expression instanceof Expr\ClassConstFetch
            || ! $expression->class instanceof Name
            || ! $expression->name instanceof Identifier
            || strtolower($expression->name->toString()) !== 'class') {
            return null;
        }

        return $expression->class->toString().'::class';
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

    private function isNull(Expr $expression): bool
    {
        return $expression instanceof Expr\ConstFetch
            && strtolower($expression->name->toString()) === 'null';
    }

    private function methodName(Node $name): ?string
    {
        return $name instanceof Identifier ? strtolower($name->toString()) : null;
    }
}

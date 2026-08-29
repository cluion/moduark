<?php

declare(strict_types=1);

namespace Cluion\Moduark\Extraction;

use Illuminate\Support\Facades\App;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use ReflectionClass;

final readonly class ProviderBindingScanner
{
    /**
     * @param class-string $provider
     * @return list<array{
     *     provider: class-string,
     *     method: string,
     *     line: int,
     *     receiver: string,
     *     abstract: array{kind: string, value: ?string},
     *     concrete: array{kind: string, value: ?string}
     * }>
     */
    public function scan(string $provider): array
    {
        $reflection = new ReflectionClass($provider);
        $file = $reflection->getFileName();
        $start = $reflection->getStartLine();
        $end = $reflection->getEndLine();

        if (! is_string($file) || ! is_int($start) || ! is_int($end)) {
            return [$this->failure($provider, 'provider_source_unavailable')];
        }

        $source = file_get_contents($file);

        if ($source === false) {
            return [$this->failure($provider, 'provider_source_unreadable')];
        }

        try {
            $statements = (new ParserFactory)->createForNewestSupportedVersion()->parse($source) ?? [];
            $collector = new ProviderBindingVisitor($provider, $start, $end);
            $statements = (new NodeTraverser(new NameResolver))->traverse($statements);
            (new NodeTraverser($collector))->traverse($statements);
        } catch (Error $error) {
            return [$this->failure(
                $provider,
                'provider_syntax_error:'.$error->getStartLine().':'.$error->getRawMessage(),
            )];
        }

        return $collector->bindings();
    }

    /**
     * @param class-string $provider
     * @return array{
     *     provider: class-string,
     *     method: string,
     *     line: int,
     *     receiver: string,
     *     abstract: array{kind: string, value: ?string},
     *     concrete: array{kind: string, value: ?string}
     * }
     */
    private function failure(string $provider, string $reason): array
    {
        return [
            'provider' => $provider,
            'method' => 'scan',
            'line' => 0,
            'receiver' => 'error',
            'abstract' => ['kind' => 'dynamic', 'value' => $reason],
            'concrete' => ['kind' => 'none', 'value' => null],
        ];
    }
}

final class ProviderBindingVisitor extends NodeVisitorAbstract
{
    /** @var list<string> */
    private const METHODS = [
        'alias',
        'bind',
        'bindIf',
        'extend',
        'instance',
        'scoped',
        'scopedIf',
        'singleton',
        'singletonIf',
        'when',
    ];

    /**
     * @var list<array{
     *     provider: class-string,
     *     method: string,
     *     line: int,
     *     receiver: string,
     *     abstract: array{kind: string, value: ?string},
     *     concrete: array{kind: string, value: ?string}
     * }>
     */
    private array $bindings = [];

    /** @param class-string $provider */
    public function __construct(
        private readonly string $provider,
        private readonly int $start,
        private readonly int $end,
    ) {
    }

    public function enterNode(Node $node): null
    {
        $line = $node->getStartLine();

        if ($line < $this->start || $line > $this->end) {
            return null;
        }

        if ($node instanceof Expr\MethodCall && $node->name instanceof Identifier) {
            $method = $node->name->toString();

            if (! in_array($method, self::METHODS, true)) {
                return null;
            }

            $this->add($method, $line, $this->methodReceiver($node->var), $node->args);

            return null;
        }

        if ($node instanceof Expr\StaticCall
            && $node->name instanceof Identifier
            && $node->class instanceof Name) {
            $method = $node->name->toString();

            if (! in_array($method, self::METHODS, true)) {
                return null;
            }

            $class = $this->resolvedName($node->class);
            $receiver = $class === App::class || $class === 'App' ? 'facade' : 'dynamic';
            $this->add($method, $line, $receiver, $node->args);
        }

        return null;
    }

    /**
     * @return list<array{
     *     provider: class-string,
     *     method: string,
     *     line: int,
     *     receiver: string,
     *     abstract: array{kind: string, value: ?string},
     *     concrete: array{kind: string, value: ?string}
     * }>
     */
    public function bindings(): array
    {
        return $this->bindings;
    }

    /** @param array<Arg|Node\VariadicPlaceholder> $arguments */
    private function add(string $method, int $line, string $receiver, array $arguments): void
    {
        $abstract = isset($arguments[0]) && $arguments[0] instanceof Arg
            ? $this->operand($arguments[0]->value)
            : ['kind' => 'dynamic', 'value' => 'missing_abstract'];
        $concrete = isset($arguments[1]) && $arguments[1] instanceof Arg
            ? $this->operand($arguments[1]->value)
            : ['kind' => 'same', 'value' => null];

        if ($method === 'when') {
            $concrete = ['kind' => 'contextual', 'value' => null];
        }

        $this->bindings[] = [
            'provider' => $this->provider,
            'method' => $method,
            'line' => $line,
            'receiver' => $receiver,
            'abstract' => $abstract,
            'concrete' => $concrete,
        ];
    }

    private function methodReceiver(Expr $receiver): string
    {
        if ($receiver instanceof Expr\PropertyFetch
            && $receiver->var instanceof Expr\Variable
            && $receiver->var->name === 'this'
            && $receiver->name instanceof Identifier
            && $receiver->name->toString() === 'app') {
            return 'provider_app';
        }

        if ($receiver instanceof Expr\FuncCall
            && $receiver->name instanceof Name
            && strtolower($receiver->name->toString()) === 'app') {
            return 'app_helper';
        }

        return 'dynamic';
    }

    /** @return array{kind: string, value: ?string} */
    private function operand(Expr $operand): array
    {
        if ($operand instanceof Expr\ClassConstFetch
            && $operand->class instanceof Name
            && $operand->name instanceof Identifier
            && strtolower($operand->name->toString()) === 'class') {
            return ['kind' => 'class', 'value' => $this->resolvedName($operand->class)];
        }

        if ($operand instanceof Scalar\String_) {
            return ['kind' => 'string', 'value' => $operand->value];
        }

        if ($operand instanceof Expr\Closure || $operand instanceof Expr\ArrowFunction) {
            return ['kind' => 'factory', 'value' => null];
        }

        if ($operand instanceof Expr\New_ && $operand->class instanceof Name) {
            return ['kind' => 'class', 'value' => $this->resolvedName($operand->class)];
        }

        if ($operand instanceof Scalar\Int_ || $operand instanceof Scalar\Float_) {
            return ['kind' => 'scalar', 'value' => (string) $operand->value];
        }

        if ($operand instanceof Expr\ConstFetch) {
            $name = strtolower($operand->name->toString());

            if ($name === 'null') {
                return ['kind' => 'null', 'value' => null];
            }

            if ($name === 'true' || $name === 'false') {
                return ['kind' => 'scalar', 'value' => $name];
            }
        }

        return ['kind' => 'dynamic', 'value' => $operand::class];
    }

    private function resolvedName(Name $name): string
    {
        $resolved = $name->getAttribute('resolvedName');

        return ltrim(
            $resolved instanceof Name ? $resolved->toString() : $name->toString(),
            '\\',
        );
    }
}

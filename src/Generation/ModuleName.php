<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use Cluion\Moduark\Exceptions\ModuleGenerationFailed;

final readonly class ModuleName
{
    /**
     * PHP keywords and reserved type names are rejected even though the
     * generated entry class has a Module suffix.
     *
     * @var list<string>
     */
    private const RESERVED = [
        '__halt_compiler',
        'abstract',
        'and',
        'array',
        'as',
        'bool',
        'break',
        'callable',
        'case',
        'catch',
        'class',
        'clone',
        'const',
        'continue',
        'declare',
        'default',
        'die',
        'do',
        'echo',
        'else',
        'elseif',
        'empty',
        'enddeclare',
        'endfor',
        'endforeach',
        'endif',
        'endswitch',
        'endwhile',
        'enum',
        'eval',
        'exit',
        'extends',
        'false',
        'final',
        'finally',
        'float',
        'fn',
        'for',
        'foreach',
        'from',
        'function',
        'global',
        'goto',
        'if',
        'implements',
        'include',
        'include_once',
        'instanceof',
        'insteadof',
        'int',
        'interface',
        'isset',
        'iterable',
        'list',
        'match',
        'mixed',
        'namespace',
        'never',
        'new',
        'null',
        'object',
        'or',
        'parent',
        'print',
        'private',
        'protected',
        'public',
        'readonly',
        'require',
        'require_once',
        'resource',
        'return',
        'self',
        'static',
        'string',
        'switch',
        'throw',
        'trait',
        'true',
        'try',
        'unset',
        'use',
        'var',
        'void',
        'while',
        'xor',
        'yield',
    ];

    private function __construct(private string $value)
    {
    }

    public static function from(string $value): self
    {
        if (preg_match('/\A[A-Z][A-Za-z0-9]*\z/D', $value) !== 1) {
            throw ModuleGenerationFailed::invalidName($value);
        }

        if (in_array(strtolower($value), self::RESERVED, true)) {
            throw ModuleGenerationFailed::reservedName($value);
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function entryClass(): string
    {
        return $this->value.'Module';
    }
}

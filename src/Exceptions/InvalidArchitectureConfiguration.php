<?php

declare(strict_types=1);

namespace Cluion\Moduark\Exceptions;

use InvalidArgumentException;

final class InvalidArchitectureConfiguration extends InvalidArgumentException
{
    public static function stringRuleKeys(): self
    {
        return new self('The moduark.architecture.rules configuration must use rule IDs as string keys.');
    }

    public static function unknownRule(string $rule): self
    {
        return new self("The moduark.architecture.rules configuration contains unknown rule [{$rule}].");
    }

    public static function booleanOverride(string $rule, mixed $value): self
    {
        return new self(sprintf(
            'The moduark.architecture.rules.%s configuration must be a boolean; received %s.',
            $rule,
            get_debug_type($value),
        ));
    }
}

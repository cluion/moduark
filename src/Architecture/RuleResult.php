<?php

declare(strict_types=1);

namespace Cluion\Moduark\Architecture;

use InvalidArgumentException;

final readonly class RuleResult
{
    /**
     * @param list<Violation> $violations
     */
    public function __construct(
        private RuleId $rule,
        private array $violations = [],
    ) {
        foreach ($violations as $violation) {
            if ($violation->rule() !== $rule) {
                throw new InvalidArgumentException(sprintf(
                    'Rule result [%s] cannot contain a violation from rule [%s].',
                    $rule->value,
                    $violation->rule()->value,
                ));
            }
        }
    }

    public function rule(): RuleId
    {
        return $this->rule;
    }

    /**
     * @return list<Violation>
     */
    public function violations(): array
    {
        return $this->violations;
    }

    public function passed(): bool
    {
        return $this->violations === [];
    }

    public function hasErrors(): bool
    {
        return $this->hasSeverity(Severity::Error);
    }

    public function hasWarnings(): bool
    {
        return $this->hasSeverity(Severity::Warning);
    }

    private function hasSeverity(Severity $severity): bool
    {
        foreach ($this->violations as $violation) {
            if ($violation->severity() === $severity) {
                return true;
            }
        }

        return false;
    }
}

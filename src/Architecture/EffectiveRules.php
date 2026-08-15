<?php

declare(strict_types=1);

namespace Cluion\Moduark\Architecture;

use InvalidArgumentException;

final readonly class EffectiveRules
{
    /** @var array<string, EffectiveRule> */
    private array $rules;

    /**
     * @param list<EffectiveRule> $rules
     */
    public function __construct(array $rules)
    {
        $indexed = [];

        foreach ($rules as $rule) {
            $id = $rule->id()->value;

            if (isset($indexed[$id])) {
                throw new InvalidArgumentException("Effective rule [{$id}] was provided more than once.");
            }

            $indexed[$id] = $rule;
        }

        $this->rules = $indexed;
    }

    public function get(RuleId $id): EffectiveRule
    {
        return $this->rules[$id->value]
            ?? throw new InvalidArgumentException("Effective rule [{$id->value}] is not available.");
    }

    /**
     * @return list<EffectiveRule>
     */
    public function all(): array
    {
        return array_values($this->rules);
    }

    /**
     * @return list<EffectiveRule>
     */
    public function enabled(): array
    {
        return array_values(array_filter(
            $this->rules,
            static fn (EffectiveRule $rule): bool => $rule->enabled(),
        ));
    }

    /**
     * @return array<string, array{enabled: bool, severity: string}>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (EffectiveRule $rule): array => $rule->toArray(),
            $this->rules,
        );
    }
}

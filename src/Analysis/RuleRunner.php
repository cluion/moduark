<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis;

use Cluion\Moduark\Architecture\EffectiveArchitecture;
use InvalidArgumentException;

final readonly class RuleRunner
{
    /** @var array<string, ArchitectureRule> */
    private array $rules;

    /**
     * @param list<ArchitectureRule> $rules
     */
    public function __construct(array $rules)
    {
        $indexed = [];

        foreach ($rules as $rule) {
            $id = $rule->id()->value;

            if (isset($indexed[$id])) {
                throw new InvalidArgumentException("Architecture rule [{$id}] was registered more than once.");
            }

            $indexed[$id] = $rule;
        }

        $this->rules = $indexed;
    }

    public function run(AnalysisContext $context, EffectiveArchitecture $architecture): CheckReport
    {
        $results = [];
        $unavailable = [];

        foreach ($architecture->rules()->enabled() as $effectiveRule) {
            $rule = $this->rules[$effectiveRule->id()->value] ?? null;

            if ($rule === null) {
                $unavailable[] = $effectiveRule->id();

                continue;
            }

            $result = $rule->inspect($context, $effectiveRule->severity());

            if ($result->rule() !== $effectiveRule->id()) {
                throw new InvalidArgumentException(sprintf(
                    'Architecture rule [%s] returned a result for rule [%s].',
                    $effectiveRule->id()->value,
                    $result->rule()->value,
                ));
            }

            $results[] = $result;
        }

        return new CheckReport($architecture, $results, $unavailable);
    }
}

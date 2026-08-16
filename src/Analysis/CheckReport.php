<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis;

use Cluion\Moduark\Analysis\Baseline\BaselineStatus;
use Cluion\Moduark\Architecture\EffectiveArchitecture;
use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Architecture\Violation;

final readonly class CheckReport
{
    /**
     * @param list<RuleResult> $results
     * @param list<RuleId> $unavailableRules
     */
    public function __construct(
        private EffectiveArchitecture $architecture,
        private array $results,
        private array $unavailableRules,
        private ?BaselineStatus $baseline = null,
    ) {
    }

    public function architecture(): EffectiveArchitecture
    {
        return $this->architecture;
    }

    /**
     * @return list<RuleResult>
     */
    public function results(): array
    {
        return $this->results;
    }

    /**
     * @return list<RuleId>
     */
    public function unavailableRules(): array
    {
        return $this->unavailableRules;
    }

    public function complete(): bool
    {
        return $this->unavailableRules === [];
    }

    public function baseline(): ?BaselineStatus
    {
        return $this->baseline;
    }

    /**
     * @return list<Violation>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->results as $result) {
            array_push($violations, ...$result->violations());
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     */
    public function errors(): array
    {
        return $this->withSeverity(Severity::Error);
    }

    /**
     * @return list<Violation>
     */
    public function warnings(): array
    {
        return $this->withSeverity(Severity::Warning);
    }

    public function exitCode(ExitPolicy $policy): int
    {
        return $this->complete()
            ? $policy->exitCode($this->results)
            : ExitPolicy::TOOL_ERROR;
    }

    /**
     * @return list<Violation>
     */
    private function withSeverity(Severity $severity): array
    {
        return array_values(array_filter(
            $this->violations(),
            static fn (Violation $violation): bool => $violation->severity() === $severity,
        ));
    }
}

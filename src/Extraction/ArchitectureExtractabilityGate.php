<?php

declare(strict_types=1);

namespace Cluion\Moduark\Extraction;

use Cluion\Moduark\Analysis\CheckReport;
use Cluion\Moduark\Analysis\RawArchitectureCheck;
use Cluion\Moduark\Architecture\Level;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\Violation;
use Cluion\Moduark\Discovery\DiscoveredModule;

final readonly class ArchitectureExtractabilityGate
{
    public function __construct(private RawArchitectureCheck $architecture)
    {
    }

    /** @return list<ExtractabilityCheck> */
    public function checks(DiscoveredModule $module): array
    {
        $report = $this->architecture->check(Level::Isolated);
        $checks = [];

        foreach ($this->definitions() as $definition) {
            $violations = $this->violationsFor($report, $definition['rule'], $module->name());

            if (! $this->evaluated($report, $definition['rule'])) {
                $checks[] = $this->blocked(
                    $definition,
                    'The required raw Level 3 architecture rule was not evaluated.',
                    ['rule_not_evaluated='.$definition['rule']->value],
                );

                continue;
            }

            if ($violations !== []) {
                $checks[] = $this->blocked(
                    $definition,
                    'Architecture evidence involving this Module blocks export planning.',
                    array_map($this->violationEvidence(...), $violations),
                );

                continue;
            }

            $checks[] = new ExtractabilityCheck(
                $definition['code'],
                $definition['category'],
                ExtractabilityCheck::PASSED,
                'The raw Level 3 architecture rule found no evidence involving this Module.',
                ['rule='.$definition['rule']->value, 'violations=none'],
            );
        }

        return $checks;
    }

    private function evaluated(CheckReport $report, RuleId $rule): bool
    {
        if (! $report->architecture()->rules()->get($rule)->enabled()) {
            return false;
        }

        foreach ($report->results() as $result) {
            if ($result->rule() === $rule) {
                return true;
            }
        }

        return false;
    }

    /** @return list<Violation> */
    private function violationsFor(CheckReport $report, RuleId $rule, string $module): array
    {
        $violations = [];

        foreach ($report->results() as $result) {
            if ($result->rule() !== $rule) {
                continue;
            }

            foreach ($result->violations() as $violation) {
                if ($this->involves($violation, $module)) {
                    $violations[] = $violation;
                }
            }
        }

        return $violations;
    }

    private function involves(Violation $violation, string $module): bool
    {
        if ($violation->consumer() !== null
            && strcasecmp($violation->consumer(), $module) === 0) {
            return true;
        }

        if ($violation->target() === null) {
            return false;
        }

        $targets = preg_split('/\s*,\s*/', $violation->target());

        if ($targets === false) {
            return false;
        }

        foreach ($targets as $target) {
            if (strcasecmp($target, $module) === 0) {
                return true;
            }
        }

        return false;
    }

    private function violationEvidence(Violation $violation): string
    {
        return $violation->code().'='.json_encode(
            $violation->toArray(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
    }

    /**
     * @param array{rule: RuleId, code: string, category: string} $definition
     * @param list<string> $evidence
     */
    private function blocked(array $definition, string $message, array $evidence): ExtractabilityCheck
    {
        return new ExtractabilityCheck(
            $definition['code'],
            $definition['category'],
            ExtractabilityCheck::BLOCKED,
            $message,
            $evidence,
        );
    }

    /** @return list<array{rule: RuleId, code: string, category: string}> */
    private function definitions(): array
    {
        return [
            [
                'rule' => RuleId::UndeclaredDependencies,
                'code' => 'MOD-EXTRACT-DEPENDENCY-001',
                'category' => 'architecture_dependency',
            ],
            [
                'rule' => RuleId::CapabilityContracts,
                'code' => 'MOD-EXTRACT-CAPABILITY-001',
                'category' => 'architecture_capability',
            ],
            [
                'rule' => RuleId::DatabaseOwnership,
                'code' => 'MOD-EXTRACT-TABLE-001',
                'category' => 'architecture_table',
            ],
            [
                'rule' => RuleId::CrossModuleForeignKeys,
                'code' => 'MOD-EXTRACT-FK-001',
                'category' => 'architecture_foreign_key',
            ],
            [
                'rule' => RuleId::CrossModuleTransactions,
                'code' => 'MOD-EXTRACT-TRANSACTION-001',
                'category' => 'architecture_transaction',
            ],
            [
                'rule' => RuleId::ExplicitPublicExports,
                'code' => 'MOD-EXTRACT-EXPORT-001',
                'category' => 'architecture_export',
            ],
        ];
    }
}

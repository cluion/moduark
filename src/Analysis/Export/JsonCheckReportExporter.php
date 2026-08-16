<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Export;

use Cluion\Moduark\Analysis\CheckReport;
use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Violation;

final class JsonCheckReportExporter
{
    public function export(CheckReport $report, ExitPolicy $policy): string
    {
        $architecture = $report->architecture();
        $violations = $report->violations();
        $exitCode = $report->exitCode($policy);

        return json_encode([
            'schema_version' => 1,
            'status' => $this->status($report, $exitCode),
            'complete' => $report->complete(),
            'exit_code' => $exitCode,
            'architecture' => [
                'configured_level' => $architecture->configuredLevel()->value,
                'configured_level_label' => $architecture->configuredLevel()->label(),
                'level' => $architecture->level()->value,
                'level_label' => $architecture->level()->label(),
                'level_overridden' => $architecture->levelOverridden(),
                'rules' => $architecture->rules()->toArray(),
            ],
            'summary' => [
                'rules_evaluated' => count($report->results()),
                'violations' => count($violations),
                'errors' => count($report->errors()),
                'warnings' => count($report->warnings()),
            ],
            'suppressions' => $report->suppressions()?->toArray(),
            'baseline' => $report->baseline()?->toArray(),
            'unavailable_rules' => array_map(
                static fn (RuleId $rule): string => $rule->value,
                $report->unavailableRules(),
            ),
            'results' => array_map(
                static fn (RuleResult $result): array => [
                    'rule' => $result->rule()->value,
                    'passed' => $result->passed(),
                    'violations' => array_map(
                        static fn (Violation $violation): array => $violation->toArray(),
                        $result->violations(),
                    ),
                ],
                $report->results(),
            ),
            'error' => null,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function exportToolError(
        string $code,
        string $message,
        ?string $location = null,
        ?string $suggestion = null,
    ): string {
        return json_encode([
            'schema_version' => 1,
            'status' => 'incomplete',
            'complete' => false,
            'exit_code' => ExitPolicy::TOOL_ERROR,
            'architecture' => null,
            'summary' => [
                'rules_evaluated' => 0,
                'violations' => 0,
                'errors' => 0,
                'warnings' => 0,
            ],
            'suppressions' => null,
            'baseline' => null,
            'unavailable_rules' => [],
            'results' => [],
            'error' => [
                'code' => $code,
                'message' => $message,
                'location' => $location,
                'suggestion' => $suggestion,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function status(CheckReport $report, int $exitCode): string
    {
        if (! $report->complete()) {
            return 'incomplete';
        }

        return $exitCode === ExitPolicy::VIOLATIONS_FOUND
            ? 'violations_found'
            : 'passed';
    }
}

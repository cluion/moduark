<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use JsonException;

final readonly class NativeGeneratorBridgePlanExporter
{
    /**
     * @return array{
     *     schema_version: 1,
     *     status: 'disabled'|'planned'|'blocked',
     *     complete: true,
     *     exit_code: int,
     *     opt_in: bool,
     *     mutation: false,
     *     option: 'module',
     *     summary: array{candidates: int, ready: int, blocked: int},
     *     commands: list<array<string, mixed>>
     * }
     */
    public function payload(NativeGeneratorBridgePlan $plan): array
    {
        return [
            'schema_version' => 1,
            'status' => $plan->status(),
            'complete' => true,
            'exit_code' => $plan->exitCode(),
            'opt_in' => $plan->optedIn(),
            'mutation' => false,
            'option' => 'module',
            'summary' => [
                'candidates' => count($plan->candidates()),
                'ready' => $plan->readyCount(),
                'blocked' => $plan->blockedCount(),
            ],
            'commands' => array_map(
                static fn (NativeGeneratorBridgeCandidate $candidate): array => $candidate->toArray(),
                $plan->candidates(),
            ),
        ];
    }

    /** @throws JsonException */
    public function json(NativeGeneratorBridgePlan $plan): string
    {
        return json_encode(
            $this->payload($plan),
            JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        )."\n";
    }

    /** @return list<string> */
    public function textLines(NativeGeneratorBridgePlan $plan): array
    {
        $lines = [
            'Native generator bridge plan: '.strtoupper($plan->status()),
            'Opt-in: '.($plan->optedIn() ? 'enabled' : 'disabled'),
            'Mutation: disabled (plan-only)',
            sprintf(
                'Candidates: %d; ready: %d; blocked: %d',
                count($plan->candidates()),
                $plan->readyCount(),
                $plan->blockedCount(),
            ),
        ];

        foreach ($plan->candidates() as $candidate) {
            $lines[] = sprintf(
                '%s %s -> %s',
                $candidate->ready() ? 'READY' : 'BLOCKED',
                $candidate->command(),
                $candidate->generatorId(),
            );

            foreach ($candidate->diagnostics() as $diagnostic) {
                $lines[] = '  '.$diagnostic->code().': '.$diagnostic->message();
            }
        }

        return $lines;
    }
}

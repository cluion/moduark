<?php

declare(strict_types=1);

namespace Benchmarks;

use InvalidArgumentException;

final class GenerationPerformanceGate
{
    public const DEFAULT_MAX_MEDIAN_TOTAL_MS = 5000.0;

    /**
     * @return array{
     *     enforced: bool,
     *     status: 'not_enforced'|'passed'|'failed',
     *     max_median_total_ms: float,
     *     observed_median_total_ms: float,
     *     headroom_ms: float
     * }
     */
    public function evaluate(float $observedMedianMs, float $budgetMs, bool $enforced): array
    {
        if ($observedMedianMs < 0) {
            throw new InvalidArgumentException('Observed generation median cannot be negative.');
        }

        if ($budgetMs <= 0) {
            throw new InvalidArgumentException('Generation performance budget must be greater than zero.');
        }

        return [
            'enforced' => $enforced,
            'status' => ! $enforced
                ? 'not_enforced'
                : ($observedMedianMs <= $budgetMs ? 'passed' : 'failed'),
            'max_median_total_ms' => round($budgetMs, 3),
            'observed_median_total_ms' => round($observedMedianMs, 3),
            'headroom_ms' => round($budgetMs - $observedMedianMs, 3),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Baseline;

use Cluion\Moduark\Analysis\ArchitectureCheck;
use Cluion\Moduark\Analysis\CheckReport;
use Cluion\Moduark\Analysis\RawArchitectureCheck;
use Cluion\Moduark\Architecture\Level;
use Cluion\Moduark\Configuration\ModulesConfig;

final readonly class BaselineArchitectureCheck implements ArchitectureCheck
{
    public function __construct(
        private RawArchitectureCheck $raw,
        private ArchitectureBaselineStore $store,
        private ModulesConfig $configuration,
        private string $basePath,
    ) {
    }

    public function check(?Level $level = null): CheckReport
    {
        $report = $this->raw->check($level);
        $path = $this->configuration->baselinePath();

        if ($path === null || ! $report->complete()) {
            return $report;
        }

        $baseline = $this->store->load($path);

        return $baseline?->apply($report, $path, $this->basePath) ?? $report;
    }
}

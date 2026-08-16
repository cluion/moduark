<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Suppression;

use Cluion\Moduark\Analysis\CheckReport;
use Cluion\Moduark\Analysis\RawArchitectureCheck;
use Cluion\Moduark\Analysis\UnbaselinedArchitectureCheck;
use Cluion\Moduark\Architecture\Level;
use Cluion\Moduark\Configuration\ModulesConfig;

final readonly class SuppressionArchitectureCheck implements UnbaselinedArchitectureCheck
{
    public function __construct(
        private RawArchitectureCheck $raw,
        private SuppressionManifestStore $store,
        private ModulesConfig $configuration,
        private string $basePath,
    ) {
    }

    public function check(?Level $level = null): CheckReport
    {
        $report = $this->raw->check($level);
        $path = $this->configuration->suppressionPath();

        if ($path === null || ! $report->complete()) {
            return $report;
        }

        $manifest = $this->store->load($path);

        return $manifest?->apply($report, $path, $this->basePath) ?? $report;
    }
}

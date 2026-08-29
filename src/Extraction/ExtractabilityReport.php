<?php

declare(strict_types=1);

namespace Cluion\Moduark\Extraction;

use Cluion\Moduark\Discovery\DiscoveredModule;
use InvalidArgumentException;

final readonly class ExtractabilityReport
{
    public const SCHEMA_VERSION = 1;

    /**
     * @param list<ExtractabilityCheck> $checks
     */
    public function __construct(
        private DiscoveredModule $module,
        private array $checks,
    ) {
        $codes = [];

        foreach ($this->checks as $check) {
            if (isset($codes[$check->code()])) {
                throw new InvalidArgumentException(
                    "Extractability check [{$check->code()}] was reported more than once.",
                );
            }

            $codes[$check->code()] = true;
        }
    }

    public function module(): DiscoveredModule
    {
        return $this->module;
    }

    /** @return list<ExtractabilityCheck> */
    public function checks(): array
    {
        return $this->checks;
    }

    /** @return list<ExtractabilityCheck> */
    public function blockers(): array
    {
        return array_values(array_filter(
            $this->checks,
            static fn (ExtractabilityCheck $check): bool => $check->blocked(),
        ));
    }

    public function readyForExportDryRun(): bool
    {
        return $this->blockers() === [];
    }

    /**
     * @return array{
     *     schema_version: int,
     *     mode: string,
     *     status: string,
     *     module: array{name: string, class: string, path: string, namespace: string},
     *     checks: list<array<string, mixed>>,
     *     blockers: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'mode' => 'extractability',
            'status' => $this->readyForExportDryRun() ? 'ready_for_export_dry_run' : 'blocked',
            'module' => $this->module->toArray(),
            'checks' => array_map(
                static fn (ExtractabilityCheck $check): array => $check->toArray(),
                $this->checks,
            ),
            'blockers' => array_map(
                static fn (ExtractabilityCheck $check): array => $check->toArray(),
                $this->blockers(),
            ),
        ];
    }
}

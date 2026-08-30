<?php

declare(strict_types=1);

namespace Cluion\Moduark\Export;

final readonly class ModuleExportSetPlan
{
    public const SCHEMA_VERSION = 1;

    /** @var list<PackageExportPlan> */
    private array $packages;

    /** @var list<string> */
    private array $order;

    /** @var list<ExportPlanBlocker> */
    private array $blockers;

    /**
     * @param list<PackageExportPlan> $packages
     * @param list<string> $order
     * @param list<ExportPlanBlocker> $blockers
     */
    public function __construct(array $packages, array $order, array $blockers)
    {
        usort(
            $blockers,
            static fn (ExportPlanBlocker $left, ExportPlanBlocker $right): int =>
                strcmp($left->code(), $right->code()),
        );

        $this->packages = $packages;
        $this->order = $order;
        $this->blockers = $blockers;
    }

    public function ready(): bool
    {
        if ($this->blockers !== []) {
            return false;
        }

        foreach ($this->packages as $package) {
            if (! $package->plan()->ready()) {
                return false;
            }
        }

        return true;
    }

    /** @return list<PackageExportPlan> */
    public function packages(): array
    {
        return $this->packages;
    }

    /** @return list<string> */
    public function order(): array
    {
        return $this->order;
    }

    /** @return list<ExportPlanBlocker> */
    public function blockers(): array
    {
        return $this->blockers;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $files = 0;
        $dependencies = 0;
        $packageBlockers = 0;
        $readyPackages = 0;

        foreach ($this->packages as $package) {
            $plan = $package->plan();
            $files += count($plan->files());
            $dependencies += count($plan->dependencies());
            $packageBlockers += count($plan->blockers());
            $readyPackages += $plan->ready() ? 1 : 0;
        }

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'status' => $this->ready() ? 'planned' : 'blocked',
            'complete' => true,
            'operation' => 'export-set',
            'dry_run' => true,
            'order' => $this->order,
            'summary' => [
                'packages' => count($this->packages),
                'ready_packages' => $readyPackages,
                'files' => $files,
                'dependencies' => $dependencies,
                'blockers' => count($this->blockers) + $packageBlockers,
            ],
            'packages' => array_map(
                static fn (PackageExportPlan $package): array => $package->toArray(),
                $this->packages,
            ),
            'blockers' => array_map(
                static fn (ExportPlanBlocker $blocker): array => $blocker->toArray(),
                $this->blockers,
            ),
        ];
    }
}

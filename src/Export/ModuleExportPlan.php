<?php

declare(strict_types=1);

namespace Cluion\Moduark\Export;

use Cluion\Moduark\Discovery\DiscoveredModule;

final readonly class ModuleExportPlan
{
    public const SCHEMA_VERSION = 1;

    /** @var list<ExportPlanFile> */
    private array $files;

    /** @var list<ExportPlanDependency> */
    private array $dependencies;

    /** @var list<ExportPlanBlocker> */
    private array $blockers;

    /**
     * @param list<ExportPlanFile> $files
     * @param list<ExportPlanDependency> $dependencies
     * @param list<ExportPlanBlocker> $blockers
     */
    public function __construct(
        private DiscoveredModule $module,
        private string $target,
        private string $package,
        private string $namespace,
        private string $provider,
        array $files,
        array $dependencies,
        array $blockers,
    ) {
        usort($files, static function (ExportPlanFile $left, ExportPlanFile $right): int {
            $destination = strcmp($left->destination(), $right->destination());

            return $destination !== 0
                ? $destination
                : strcmp($left->operation(), $right->operation());
        });
        usort(
            $dependencies,
            static fn (ExportPlanDependency $left, ExportPlanDependency $right): int =>
                strcmp($left->source(), $right->source()),
        );
        usort(
            $blockers,
            static fn (ExportPlanBlocker $left, ExportPlanBlocker $right): int =>
                strcmp($left->code(), $right->code()),
        );

        $this->files = $files;
        $this->dependencies = $dependencies;
        $this->blockers = $blockers;
    }

    public function module(): DiscoveredModule
    {
        return $this->module;
    }

    public function ready(): bool
    {
        return $this->blockers === [];
    }

    public function target(): string
    {
        return $this->target;
    }

    public function package(): string
    {
        return $this->package;
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    /** @return list<ExportPlanFile> */
    public function files(): array
    {
        return $this->files;
    }

    /** @return list<ExportPlanDependency> */
    public function dependencies(): array
    {
        return $this->dependencies;
    }

    /** @return list<ExportPlanBlocker> */
    public function blockers(): array
    {
        return $this->blockers;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'status' => $this->ready() ? 'planned' : 'blocked',
            'complete' => true,
            'operation' => 'export',
            'dry_run' => true,
            'module' => $this->module->toArray(),
            'package' => [
                'name' => $this->package,
                'namespace' => $this->namespace,
                'provider' => $this->provider,
                'target' => $this->target,
            ],
            'summary' => [
                'files' => count($this->files),
                'dependencies' => count($this->dependencies),
                'manual_dependencies' => count(array_filter(
                    $this->dependencies,
                    static fn (ExportPlanDependency $dependency): bool =>
                        $dependency->status() === ExportPlanDependency::MANUAL,
                )),
                'blockers' => count($this->blockers),
            ],
            'files' => array_map(
                static fn (ExportPlanFile $file): array => $file->toArray(),
                $this->files,
            ),
            'dependencies' => array_map(
                static fn (ExportPlanDependency $dependency): array => $dependency->toArray(),
                $this->dependencies,
            ),
            'blockers' => array_map(
                static fn (ExportPlanBlocker $blocker): array => $blocker->toArray(),
                $this->blockers,
            ),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Package;

use Cluion\Moduark\Exceptions\PackageModuleDiscoveryFailed;
use JsonException;

final readonly class PackageModuleCatalog
{
    public const SCHEMA_VERSION = 1;

    /** @var list<PackageModuleDescriptor> */
    private array $modules;

    /** @param list<PackageModuleDescriptor> $modules */
    public function __construct(array $modules)
    {
        $byName = [];
        $byClass = [];

        foreach ($modules as $module) {
            $name = strtolower($module->name());
            $moduleClass = strtolower($module->moduleClass());

            if (isset($byClass[$moduleClass])) {
                throw PackageModuleDiscoveryFailed::duplicateClass(
                    $module->moduleClass(),
                    $byClass[$moduleClass]->package(),
                    $module->package(),
                );
            }

            if (isset($byName[$name])) {
                throw PackageModuleDiscoveryFailed::duplicateName(
                    $module->name(),
                    $byName[$name]->package(),
                    $module->package(),
                );
            }

            $byName[$name] = $module;
            $byClass[$moduleClass] = $module;
        }

        usort($modules, static function (
            PackageModuleDescriptor $left,
            PackageModuleDescriptor $right,
        ): int {
            return [$left->package(), strtolower($left->name()), $left->name()]
                <=> [$right->package(), strtolower($right->name()), $right->name()];
        });

        $this->modules = $modules;
    }

    /** @return list<PackageModuleDescriptor> */
    public function all(): array
    {
        return $this->modules;
    }

    /**
     * @return array{
     *     schema_version: int,
     *     modules: list<array{
     *         package: string,
     *         name: string,
     *         class: class-string<\Cluion\Moduark\Module>,
     *         path: string,
     *         namespace: string
     *     }>
     * }
     */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'modules' => array_map(
                static fn (PackageModuleDescriptor $module): array => $module->toArray(),
                $this->modules,
            ),
        ];
    }

    /** @throws JsonException */
    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this->toArray(), JSON_THROW_ON_ERROR));
    }
}

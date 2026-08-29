<?php

declare(strict_types=1);

namespace Cluion\Moduark\Package;

use Cluion\Moduark\Exceptions\PackageModuleDiscoveryFailed;
use Cluion\Moduark\Module;
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

    /** @return list<class-string<Module>> */
    public function moduleClasses(): array
    {
        return array_map(
            static fn (PackageModuleDescriptor $module): string => $module->moduleClass(),
            $this->modules,
        );
    }

    public function find(string $name): ?PackageModuleDescriptor
    {
        foreach ($this->modules as $module) {
            if (strcasecmp($module->name(), $name) === 0) {
                return $module;
            }
        }

        return null;
    }

    /** @param class-string<Module> $moduleClass */
    public function containsClass(string $moduleClass): bool
    {
        foreach ($this->modules as $module) {
            if (strcasecmp($module->moduleClass(), $moduleClass) === 0) {
                return true;
            }
        }

        return false;
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

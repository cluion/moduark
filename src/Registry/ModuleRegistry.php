<?php

declare(strict_types=1);

namespace Cluion\Moduark\Registry;

use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Exceptions\ModuleDiscoveryFailed;
use Cluion\Moduark\Module;

final readonly class ModuleRegistry
{
    /**
     * @var list<DiscoveredModule>
     */
    private array $modules;

    /**
     * @param list<DiscoveredModule> $modules
     */
    public function __construct(array $modules)
    {
        $byName = [];
        $byClass = [];

        foreach ($modules as $module) {
            $nameKey = strtolower($module->name());
            $classKey = strtolower($module->moduleClass());

            if (isset($byName[$nameKey])) {
                throw ModuleDiscoveryFailed::duplicateName(
                    $module->name(),
                    $byName[$nameKey]->path(),
                    $module->path(),
                );
            }

            if (isset($byClass[$classKey])) {
                throw ModuleDiscoveryFailed::duplicateClass(
                    $module->moduleClass(),
                    $byClass[$classKey]->path(),
                    $module->path(),
                );
            }

            $byName[$nameKey] = $module;
            $byClass[$classKey] = $module;
        }

        usort($modules, static function (DiscoveredModule $left, DiscoveredModule $right): int {
            $caseInsensitive = strcasecmp($left->name(), $right->name());

            return $caseInsensitive !== 0
                ? $caseInsensitive
                : strcmp($left->name(), $right->name());
        });

        $this->modules = $modules;
    }

    /**
     * @return list<DiscoveredModule>
     */
    public function all(): array
    {
        return $this->modules;
    }

    /**
     * @return list<class-string<Module>>
     */
    public function moduleClasses(): array
    {
        return array_map(
            static fn (DiscoveredModule $module): string => $module->moduleClass(),
            $this->modules,
        );
    }

    public function find(string $name): ?DiscoveredModule
    {
        foreach ($this->modules as $module) {
            if (strcasecmp($module->name(), $name) === 0) {
                return $module;
            }
        }

        return null;
    }

    /**
     * @return list<array{
     *     name: string,
     *     class: class-string<Module>,
     *     path: string,
     *     namespace: string
     * }>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (DiscoveredModule $module): array => $module->toArray(),
            $this->modules,
        );
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Lifecycle;

use Cluion\Moduark\Exceptions\CircularModuleDependency;
use Cluion\Moduark\Exceptions\InvalidModuleMetadata;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Module;

final class ModuleOrderer
{
    private const VISITING = 1;

    private const VISITED = 2;

    /**
     * @param list<ModuleDescriptor> $descriptors
     * @return list<ModuleDescriptor>
     */
    public function order(array $descriptors): array
    {
        $byClass = [];

        foreach ($descriptors as $descriptor) {
            $moduleClass = $descriptor->moduleClass();

            if (isset($byClass[$moduleClass])) {
                throw InvalidModuleMetadata::duplicateModule($moduleClass);
            }

            $byClass[$moduleClass] = $descriptor;
        }

        foreach ($descriptors as $descriptor) {
            foreach ($descriptor->dependencies() as $dependency) {
                if (! isset($byClass[$dependency])) {
                    throw InvalidModuleMetadata::missingDependency($descriptor->moduleClass(), $dependency);
                }
            }
        }

        $states = [];
        $stack = [];
        $ordered = [];

        foreach ($descriptors as $descriptor) {
            $this->visit($descriptor->moduleClass(), $byClass, $states, $stack, $ordered);
        }

        return $ordered;
    }

    /**
     * @param class-string<Module> $moduleClass
     * @param array<class-string<Module>, ModuleDescriptor> $byClass
     * @param array<class-string<Module>, int> $states
     * @param list<class-string<Module>> $stack
     * @param list<ModuleDescriptor> $ordered
     */
    private function visit(
        string $moduleClass,
        array $byClass,
        array &$states,
        array &$stack,
        array &$ordered,
    ): void {
        if (($states[$moduleClass] ?? null) === self::VISITED) {
            return;
        }

        if (($states[$moduleClass] ?? null) === self::VISITING) {
            $cycleStart = array_search($moduleClass, $stack, true);
            $cycle = array_slice($stack, is_int($cycleStart) ? $cycleStart : 0);
            $cycle[] = $moduleClass;

            throw new CircularModuleDependency($cycle);
        }

        $states[$moduleClass] = self::VISITING;
        $stack[] = $moduleClass;

        foreach ($byClass[$moduleClass]->dependencies() as $dependency) {
            $this->visit($dependency, $byClass, $states, $stack, $ordered);
        }

        array_pop($stack);
        $states[$moduleClass] = self::VISITED;
        $ordered[] = $byClass[$moduleClass];
    }
}

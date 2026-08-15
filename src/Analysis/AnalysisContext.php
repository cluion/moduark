<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis;

use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use InvalidArgumentException;

final readonly class AnalysisContext
{
    /** @var list<ModuleDescriptor> */
    private array $descriptors;

    /** @var array<class-string<Module>, DiscoveredModule> */
    private array $modulesByClass;

    /**
     * @param list<ModuleDescriptor> $descriptors
     */
    public function __construct(ModuleRegistry $registry, array $descriptors)
    {
        $modulesByClass = [];

        foreach ($registry->all() as $module) {
            $modulesByClass[$module->moduleClass()] = $module;
        }

        $descriptorsByClass = [];

        foreach ($descriptors as $descriptor) {
            $moduleClass = $descriptor->moduleClass();

            if (! isset($modulesByClass[$moduleClass])) {
                throw new InvalidArgumentException(
                    "Module descriptor [{$moduleClass}] does not belong to the analyzed registry.",
                );
            }

            if (isset($descriptorsByClass[$moduleClass])) {
                throw new InvalidArgumentException(
                    "Module descriptor [{$moduleClass}] was provided more than once for analysis.",
                );
            }

            $descriptorsByClass[$moduleClass] = $descriptor;
        }

        $orderedDescriptors = [];

        foreach ($modulesByClass as $moduleClass => $module) {
            if (! isset($descriptorsByClass[$moduleClass])) {
                throw new InvalidArgumentException(
                    "Discovered Module [{$module->name()}] has no descriptor for analysis.",
                );
            }

            $orderedDescriptors[] = $descriptorsByClass[$moduleClass];
        }

        $this->descriptors = $orderedDescriptors;
        $this->modulesByClass = $modulesByClass;
    }

    /**
     * @return list<ModuleDescriptor>
     */
    public function descriptors(): array
    {
        return $this->descriptors;
    }

    /**
     * @param class-string<Module> $moduleClass
     */
    public function module(string $moduleClass): ?DiscoveredModule
    {
        return $this->modulesByClass[$moduleClass] ?? null;
    }

    /**
     * @param class-string<Module> $moduleClass
     */
    public function displayName(string $moduleClass): string
    {
        $module = $this->module($moduleClass);

        if ($module !== null) {
            return $module->name();
        }

        $separator = strrpos($moduleClass, '\\');
        $className = $separator === false ? $moduleClass : substr($moduleClass, $separator + 1);

        return str_ends_with($className, 'Module')
            ? substr($className, 0, -strlen('Module'))
            : $className;
    }
}

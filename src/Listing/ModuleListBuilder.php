<?php

declare(strict_types=1);

namespace Cluion\Moduark\Listing;

use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;

final readonly class ModuleListBuilder
{
    public function __construct(
        private ModuleRegistry $registry,
        private ModuleMetadataCompiler $compiler,
        private ModulesConfig $configuration,
    ) {
    }

    /**
     * @return list<array{string, string, int, string, string, string}>
     */
    public function rows(): array
    {
        $discovered = $this->registry->all();
        $namesByClass = [];

        foreach ($discovered as $module) {
            $namesByClass[$module->moduleClass()] = $module->name();
        }

        $descriptors = $this->compiler->compileAll($this->registry->moduleClasses());
        $rows = [];

        foreach ($descriptors as $index => $descriptor) {
            $dependencies = array_map(
                fn (string $dependency): string => $namesByClass[$dependency]
                    ?? $this->classDisplayName($dependency),
                $descriptor->dependencies(),
            );

            $rows[] = [
                $discovered[$index]->name(),
                'enabled',
                $this->configuration->level(),
                $dependencies === [] ? '—' : implode(', ', $dependencies),
                '—',
                '—',
            ];
        }

        return $rows;
    }

    /**
     * @param class-string<Module> $moduleClass
     */
    private function classDisplayName(string $moduleClass): string
    {
        $separator = strrpos($moduleClass, '\\');
        $className = $separator === false ? $moduleClass : substr($moduleClass, $separator + 1);

        return str_ends_with($className, 'Module')
            ? substr($className, 0, -strlen('Module'))
            : $className;
    }
}

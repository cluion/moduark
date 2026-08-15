<?php

declare(strict_types=1);

namespace Cluion\Moduark\Graph;

use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;

final readonly class ModuleGraphBuilder
{
    public function __construct(
        private ModuleRegistry $registry,
        private ModuleMetadataCompiler $compiler,
    ) {
    }

    public function build(): ModuleGraph
    {
        $nodes = [];
        $nodesByClass = [];

        foreach ($this->registry->all() as $module) {
            $node = new ModuleGraphNode(
                $module->name(),
                $module->moduleClass(),
                $module->path(),
                true,
            );
            $nodes[] = $node;
            $nodesByClass[$module->moduleClass()] = $node;
        }

        $edges = [];
        $descriptors = $this->compiler->compileAll($this->registry->moduleClasses());

        foreach ($descriptors as $descriptor) {
            foreach ($descriptor->dependencies() as $dependency) {
                if (! isset($nodesByClass[$dependency])) {
                    $missingNode = new ModuleGraphNode(
                        $this->displayName($dependency),
                        $dependency,
                        null,
                        false,
                    );
                    $nodes[] = $missingNode;
                    $nodesByClass[$dependency] = $missingNode;
                }

                $edges[] = new ModuleGraphEdge(
                    $descriptor->moduleClass(),
                    $dependency,
                    $descriptor->moduleClass().'::dependencies()',
                );
            }
        }

        return new ModuleGraph($nodes, $edges);
    }

    /**
     * @param class-string<Module> $moduleClass
     */
    private function displayName(string $moduleClass): string
    {
        $separator = strrpos($moduleClass, '\\');
        $className = $separator === false ? $moduleClass : substr($moduleClass, $separator + 1);

        return str_ends_with($className, 'Module')
            ? substr($className, 0, -strlen('Module'))
            : $className;
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Export;

use Cluion\Moduark\Exceptions\CircularModuleDependency;
use Cluion\Moduark\Exceptions\InvalidModuleMetadata;
use Cluion\Moduark\Lifecycle\ModuleOrderer;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Registry\ModuleRegistry;
use InvalidArgumentException;

final readonly class ModuleExportSetPlanner
{
    public function __construct(
        private ModuleRegistry $registry,
        private ModuleMetadataCompiler $compiler,
        private ModuleOrderer $orderer,
        private ModuleExportPlanner $modulePlanner,
    ) {
    }

    /**
     * @param list<string> $packageMappings
     * @param list<string> $targetMappings
     */
    public function plan(array $packageMappings, array $targetMappings): ModuleExportSetPlan
    {
        if ($packageMappings === []) {
            throw new InvalidArgumentException('Package-set export requires at least one --package mapping.');
        }

        $selections = $this->selections($packageMappings);
        $targets = $this->targets($targetMappings, $selections);
        $descriptors = $this->descriptors($selections);
        $blockers = $this->setBlockers($selections, $targets, $descriptors);
        $ordered = $descriptors;
        $order = [];

        usort(
            $ordered,
            fn (ModuleDescriptor $left, ModuleDescriptor $right): int =>
                strcmp($this->moduleName($left->moduleClass()), $this->moduleName($right->moduleClass())),
        );

        if (! $this->hasBlocker($blockers, 'MOD-EXPORT-SET-CLOSURE-001')) {
            try {
                $ordered = $this->orderer->order($ordered);
                $order = array_map(
                    fn (ModuleDescriptor $descriptor): string => $this->moduleName($descriptor->moduleClass()),
                    $ordered,
                );
            } catch (CircularModuleDependency|InvalidModuleMetadata $exception) {
                $blockers[] = new ExportPlanBlocker(
                    'MOD-EXPORT-SET-ORDER-001',
                    'The selected package set cannot be dependency ordered.',
                    [$exception->getMessage()],
                );
                $order = [];
            }
        }

        $packages = [];

        foreach ($ordered as $descriptor) {
            $classKey = strtolower($descriptor->moduleClass());
            $selection = $selections[$classKey];
            $dependencyMappings = [];

            foreach ($descriptor->dependencies() as $dependency) {
                $dependencySelection = $selections[strtolower($dependency)] ?? null;

                if ($dependencySelection !== null) {
                    $dependencyMappings[] = $dependencySelection->toString();
                }
            }

            $plan = $this->modulePlanner->plan(
                $selection->module(),
                $targets[$classKey],
                $selection->package(),
                $selection->namespace(),
                $dependencyMappings,
            );
            $packages[] = new PackageExportPlan($plan, $selection->constraint());
        }

        return new ModuleExportSetPlan($packages, $order, $blockers);
    }

    /**
     * @param list<string> $mappings
     * @return array<string, ExportDependencyMapping>
     */
    private function selections(array $mappings): array
    {
        $selections = [];
        $packages = [];
        $namespaces = [];

        foreach ($mappings as $value) {
            $mapping = ExportDependencyMapping::fromString($value);
            $module = $this->registry->find($mapping->module());

            if ($module === null) {
                throw new InvalidArgumentException(
                    "Unknown package-set Module [{$mapping->module()}].",
                );
            }

            $classKey = strtolower($module->moduleClass());
            $packageKey = strtolower($mapping->package());
            $namespaceKey = strtolower($mapping->namespace());

            if (isset($selections[$classKey])) {
                throw new InvalidArgumentException(
                    "Duplicate package-set mapping for Module [{$module->name()}].",
                );
            }

            if (isset($packages[$packageKey])) {
                throw new InvalidArgumentException(
                    "Duplicate package-set Composer package [{$mapping->package()}].",
                );
            }

            if (isset($namespaces[$namespaceKey])) {
                throw new InvalidArgumentException(
                    "Duplicate package-set namespace [{$mapping->namespace()}].",
                );
            }

            if (in_array($mapping->package(), ['cluion/moduark', 'illuminate/support'], true)) {
                throw new InvalidArgumentException(
                    "Package-set output [{$mapping->package()}] conflicts with a generated runtime requirement.",
                );
            }

            $selections[$classKey] = ExportDependencyMapping::fromString(sprintf(
                '%s=%s:%s=>%s',
                $module->name(),
                $mapping->package(),
                $mapping->constraint(),
                $mapping->namespace(),
            ));
            $packages[$packageKey] = true;
            $namespaces[$namespaceKey] = true;
        }

        return $selections;
    }

    /**
     * @param list<string> $mappings
     * @param array<string, ExportDependencyMapping> $selections
     * @return array<string, string>
     */
    private function targets(array $mappings, array $selections): array
    {
        $targets = [];
        $paths = [];

        foreach ($mappings as $value) {
            $mapping = ExportTargetMapping::fromString($value);
            $module = $this->registry->find($mapping->module());

            if ($module === null) {
                throw new InvalidArgumentException(
                    "Unknown package-set target Module [{$mapping->module()}].",
                );
            }

            $classKey = strtolower($module->moduleClass());

            if (! isset($selections[$classKey])) {
                throw new InvalidArgumentException(
                    "Package-set target Module [{$module->name()}] has no --package mapping.",
                );
            }

            if (isset($targets[$classKey])) {
                throw new InvalidArgumentException(
                    "Duplicate package-set target for Module [{$module->name()}].",
                );
            }

            $pathKey = strtolower($mapping->target());

            if (isset($paths[$pathKey])) {
                throw new InvalidArgumentException(
                    "Duplicate package-set target path [{$mapping->target()}].",
                );
            }

            $targets[$classKey] = $mapping->target();
            $paths[$pathKey] = true;
        }

        foreach ($selections as $classKey => $selection) {
            if (! isset($targets[$classKey])) {
                throw new InvalidArgumentException(
                    "Package-set Module [{$selection->module()}] requires one --target mapping.",
                );
            }
        }

        return $targets;
    }

    /**
     * @param array<string, ExportDependencyMapping> $selections
     * @return list<ModuleDescriptor>
     */
    private function descriptors(array $selections): array
    {
        $descriptors = [];

        foreach (array_keys($selections) as $classKey) {
            foreach ($this->registry->all() as $module) {
                if (strtolower($module->moduleClass()) === $classKey) {
                    $descriptors[] = $this->compiler->compile($module->moduleClass());
                    break;
                }
            }
        }

        return $descriptors;
    }

    /**
     * @param array<string, ExportDependencyMapping> $selections
     * @param array<string, string> $targets
     * @param list<ModuleDescriptor> $descriptors
     * @return list<ExportPlanBlocker>
     */
    private function setBlockers(array $selections, array $targets, array $descriptors): array
    {
        $missing = [];

        foreach ($descriptors as $descriptor) {
            foreach ($descriptor->dependencies() as $dependency) {
                if (! isset($selections[strtolower($dependency)])) {
                    $missing[] = sprintf(
                        '%s->%s',
                        $this->moduleName($descriptor->moduleClass()),
                        $this->moduleName($dependency),
                    );
                }
            }
        }

        $blockers = $missing === [] ? [] : [new ExportPlanBlocker(
            'MOD-EXPORT-SET-CLOSURE-001',
            'Every selected Module dependency must have a package and target in the set.',
            $missing,
        )];
        $overlaps = [];
        $entries = array_values($targets);
        usort($entries, static function (string $left, string $right): int {
            $caseInsensitive = strcasecmp($left, $right);

            return $caseInsensitive !== 0 ? $caseInsensitive : strcmp($left, $right);
        });

        foreach ($entries as $index => $left) {
            foreach (array_slice($entries, $index + 1) as $right) {
                $leftKey = strtolower($left);
                $rightKey = strtolower($right);

                if (str_starts_with($rightKey.'/', $leftKey.'/')
                    || str_starts_with($leftKey.'/', $rightKey.'/')) {
                    $overlaps[] = $left.'<->'.$right;
                }
            }
        }

        if ($overlaps !== []) {
            $blockers[] = new ExportPlanBlocker(
                'MOD-EXPORT-SET-TARGET-001',
                'Package-set targets must not overlap.',
                $overlaps,
            );
        }

        return $blockers;
    }

    /** @param list<ExportPlanBlocker> $blockers */
    private function hasBlocker(array $blockers, string $code): bool
    {
        foreach ($blockers as $blocker) {
            if ($blocker->code() === $code) {
                return true;
            }
        }

        return false;
    }

    private function moduleName(string $moduleClass): string
    {
        foreach ($this->registry->all() as $module) {
            if ($module->moduleClass() === $moduleClass) {
                return $module->name();
            }
        }

        return $moduleClass;
    }
}

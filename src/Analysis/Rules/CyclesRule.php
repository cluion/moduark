<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Rules;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\ArchitectureRule;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Architecture\Violation;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Module;

final class CyclesRule implements ArchitectureRule
{
    public function id(): RuleId
    {
        return RuleId::Cycles;
    }

    public function inspect(AnalysisContext $context, Severity $severity): RuleResult
    {
        $violations = [];

        foreach ($this->cyclicComponents($context->descriptors()) as $component) {
            $names = array_map(
                static fn (string $moduleClass): string => $context->displayName($moduleClass),
                $component,
            );
            $consumer = $context->module($component[0]);

            $violations[] = new Violation(
                $this->id(),
                'MOD-CYCLE-001',
                $severity,
                sprintf('Circular Module dependency detected among [%s].', implode(', ', $names)),
                $consumer?->path(),
                null,
                $names[0],
                implode(', ', $names),
                implode(', ', $component),
                'Remove or invert at least one dependency inside the circular component.',
            );
        }

        return new RuleResult($this->id(), $violations);
    }

    /**
     * @param list<ModuleDescriptor> $descriptors
     * @return list<list<class-string<Module>>>
     */
    private function cyclicComponents(array $descriptors): array
    {
        $graph = [];

        foreach ($descriptors as $descriptor) {
            $graph[$descriptor->moduleClass()] = $descriptor->dependencies();
        }

        foreach ($graph as $moduleClass => $dependencies) {
            $existingDependencies = array_values(array_filter(
                $dependencies,
                static fn (string $dependency): bool => isset($graph[$dependency]),
            ));
            sort($existingDependencies, SORT_STRING);
            $graph[$moduleClass] = $existingDependencies;
        }

        ksort($graph, SORT_STRING);

        $nextIndex = 0;
        $indices = [];
        $lowLinks = [];
        $stack = [];
        $onStack = [];
        $components = [];

        foreach (array_keys($graph) as $moduleClass) {
            if (! isset($indices[$moduleClass])) {
                $this->visit(
                    $moduleClass,
                    $graph,
                    $nextIndex,
                    $indices,
                    $lowLinks,
                    $stack,
                    $onStack,
                    $components,
                );
            }
        }

        usort($components, static fn (array $left, array $right): int => strcmp($left[0], $right[0]));

        return $components;
    }

    /**
     * @param class-string<Module> $moduleClass
     * @param array<class-string<Module>, list<class-string<Module>>> $graph
     * @param array<class-string<Module>, int> $indices
     * @param array<class-string<Module>, int> $lowLinks
     * @param list<class-string<Module>> $stack
     * @param array<class-string<Module>, true> $onStack
     * @param list<list<class-string<Module>>> $components
     */
    private function visit(
        string $moduleClass,
        array $graph,
        int &$nextIndex,
        array &$indices,
        array &$lowLinks,
        array &$stack,
        array &$onStack,
        array &$components,
    ): void {
        $indices[$moduleClass] = $nextIndex;
        $lowLinks[$moduleClass] = $nextIndex;
        $nextIndex++;
        $stack[] = $moduleClass;
        $onStack[$moduleClass] = true;

        foreach ($graph[$moduleClass] as $dependency) {
            if (! isset($indices[$dependency])) {
                $this->visit(
                    $dependency,
                    $graph,
                    $nextIndex,
                    $indices,
                    $lowLinks,
                    $stack,
                    $onStack,
                    $components,
                );
                $lowLinks[$moduleClass] = min($lowLinks[$moduleClass], $lowLinks[$dependency]);
            } elseif (isset($onStack[$dependency])) {
                $lowLinks[$moduleClass] = min($lowLinks[$moduleClass], $indices[$dependency]);
            }
        }

        if ($lowLinks[$moduleClass] !== $indices[$moduleClass]) {
            return;
        }

        $component = [];

        do {
            $member = array_pop($stack);

            if ($member === null) {
                return;
            }

            unset($onStack[$member]);
            $component[] = $member;
        } while ($member !== $moduleClass);

        sort($component, SORT_STRING);

        if (count($component) > 1 || in_array($moduleClass, $graph[$moduleClass], true)) {
            $components[] = $component;
        }
    }
}

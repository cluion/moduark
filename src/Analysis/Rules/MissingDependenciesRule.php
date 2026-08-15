<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Rules;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\ArchitectureRule;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Architecture\Violation;

final class MissingDependenciesRule implements ArchitectureRule
{
    public function id(): RuleId
    {
        return RuleId::MissingDependencies;
    }

    public function inspect(AnalysisContext $context, Severity $severity): RuleResult
    {
        $violations = [];

        foreach ($context->descriptors() as $descriptor) {
            $consumer = $context->module($descriptor->moduleClass());

            foreach ($descriptor->dependencies() as $dependency) {
                if ($context->module($dependency) !== null) {
                    continue;
                }

                $consumerName = $consumer?->name() ?? $context->displayName($descriptor->moduleClass());
                $targetName = $context->displayName($dependency);

                $violations[] = new Violation(
                    $this->id(),
                    'MOD-DEPENDENCY-001',
                    $severity,
                    "Module [{$consumerName}] depends on missing Module [{$targetName}].",
                    $consumer?->path(),
                    null,
                    $consumerName,
                    $targetName,
                    $dependency,
                    'Discover the dependency Module or remove its declaration.',
                );
            }
        }

        return new RuleResult($this->id(), $violations);
    }
}

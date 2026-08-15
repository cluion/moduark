<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis;

use Cluion\Moduark\Architecture\Level;
use Cluion\Moduark\Architecture\RuleResolver;
use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Registry\ModuleRegistry;

final readonly class ArchitectureChecker implements ArchitectureCheck
{
    public function __construct(
        private ModuleRegistry $registry,
        private ModuleMetadataCompiler $compiler,
        private ModulesConfig $configuration,
        private RuleResolver $resolver,
        private RuleRunner $runner,
    ) {
    }

    public function check(?Level $level = null): CheckReport
    {
        $context = new AnalysisContext(
            $this->registry,
            $this->compiler->compileAll($this->registry->moduleClasses()),
        );

        return $this->runner->run(
            $context,
            $this->resolver->resolve($this->configuration, $level),
        );
    }
}

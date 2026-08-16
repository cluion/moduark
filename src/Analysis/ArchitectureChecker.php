<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis;

use Cluion\Moduark\Analysis\Source\SourceIndex;
use Cluion\Moduark\Analysis\Source\SourceIndexBuilder;
use Cluion\Moduark\Architecture\Level;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RuleResolver;
use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Registry\ModuleRegistry;

final readonly class ArchitectureChecker implements RawArchitectureCheck
{
    public function __construct(
        private ModuleRegistry $registry,
        private ModuleMetadataCompiler $compiler,
        private SourceIndexBuilder $sourceIndexBuilder,
        private ModulesConfig $configuration,
        private RuleResolver $resolver,
        private RuleRunner $runner,
    ) {
    }

    public function check(?Level $level = null): CheckReport
    {
        $architecture = $this->resolver->resolve($this->configuration, $level);
        $descriptors = $this->compiler->compileAll($this->registry->moduleClasses());
        $rules = $architecture->rules();
        $needsSourceIndex = $rules->get(RuleId::UndeclaredDependencies)->enabled()
            || $rules->get(RuleId::InternalApiAccess)->enabled()
            || $rules->get(RuleId::AdapterBoundaries)->enabled()
            || $rules->get(RuleId::CrossModuleModelAccess)->enabled()
            || $rules->get(RuleId::DatabaseOwnership)->enabled()
            || $rules->get(RuleId::MigrationOwnership)->enabled()
            || $rules->get(RuleId::CrossModuleForeignKeys)->enabled();
        $sourceIndex = $needsSourceIndex
            ? $this->sourceIndexBuilder->build()
            : new SourceIndex([], []);
        $context = new AnalysisContext(
            $this->registry,
            $descriptors,
            $sourceIndex,
        );

        return $this->runner->run(
            $context,
            $architecture,
        );
    }
}

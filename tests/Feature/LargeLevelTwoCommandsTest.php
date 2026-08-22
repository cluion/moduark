<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Analysis\ArchitectureCheck;
use Cluion\Moduark\Analysis\ArchitectureChecker;
use Cluion\Moduark\Analysis\Boundary\ConventionPublicApi;
use Cluion\Moduark\Analysis\RuleRunner;
use Cluion\Moduark\Analysis\Rules\AdapterBoundariesRule;
use Cluion\Moduark\Analysis\Rules\CapabilityContractsRule;
use Cluion\Moduark\Analysis\Rules\CyclesRule;
use Cluion\Moduark\Analysis\Rules\InternalApiAccessRule;
use Cluion\Moduark\Analysis\Rules\MissingDependenciesRule;
use Cluion\Moduark\Analysis\Rules\UndeclaredDependenciesRule;
use Cluion\Moduark\Analysis\Rules\UniqueModuleIdentityRule;
use Cluion\Moduark\Analysis\Rules\ValidModuleStructureRule;
use Cluion\Moduark\Analysis\Source\SourceIndexBuilder;
use Cluion\Moduark\Architecture\RulePresets;
use Cluion\Moduark\Architecture\RuleResolver;
use Cluion\Moduark\Capabilities\CapabilityResolver;
use Cluion\Moduark\Graph\CapabilityGraphBuilder;
use Cluion\Moduark\Graph\CombinedGraphBuilder;
use Cluion\Moduark\Graph\ModuleGraphBuilder;
use Cluion\Moduark\Inspection\ModuleInspectionBuilder;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Tests\Fixtures\LargeLevelTwo\LargeLevelTwoFixture;
use Tests\TestCase;

final class LargeLevelTwoCommandsTest extends TestCase
{
    public function test_level_two_commands_expose_the_large_fixture(): void
    {
        $this->useLargeFixture();

        $this->command('moduark:check --level=2')
            ->expectsOutputToContain(
                'Architecture check passed: 8 rules evaluated at Level 2 (Decoupled).',
            )
            ->assertSuccessful();
        $this->command('moduark:graph --view=combined')
            ->expectsOutputToContain('Checkout -[requires]-> CustomerLookup')
            ->expectsOutputToContain('Customer -[provides]-> CustomerLookup')
            ->expectsOutputToContain('Returns -[depends]-> Payment')
            ->assertSuccessful();
        $this->command('moduark:inspect Returns')
            ->expectsOutputToContain('2 (Decoupled)')
            ->expectsOutputToContain('Provider: Payment')
            ->expectsOutputToContain('Public API (convention)')
            ->assertSuccessful();
    }

    private function useLargeFixture(): void
    {
        $registry = LargeLevelTwoFixture::registry();
        $configuration = LargeLevelTwoFixture::configuration();
        $compiler = new ModuleMetadataCompiler;
        $sourceIndexBuilder = new SourceIndexBuilder($registry);
        $publicApi = new ConventionPublicApi;
        $resolver = new RuleResolver(new RulePresets);
        $moduleBuilder = new ModuleGraphBuilder($registry, $compiler);
        $capabilityBuilder = new CapabilityGraphBuilder(
            $registry,
            $compiler,
            new CapabilityResolver,
        );
        $combinedBuilder = new CombinedGraphBuilder($moduleBuilder, $capabilityBuilder);

        $this->application()->instance(
            ArchitectureCheck::class,
            new ArchitectureChecker(
                $registry,
                $compiler,
                $sourceIndexBuilder,
                $configuration,
                $resolver,
                new RuleRunner([
                    new ValidModuleStructureRule,
                    new UniqueModuleIdentityRule,
                    new MissingDependenciesRule,
                    new UndeclaredDependenciesRule,
                    new CyclesRule,
                    new InternalApiAccessRule($publicApi),
                    new CapabilityContractsRule,
                    new AdapterBoundariesRule,
                ]),
            ),
        );
        $this->application()->instance(ModuleGraphBuilder::class, $moduleBuilder);
        $this->application()->instance(CapabilityGraphBuilder::class, $capabilityBuilder);
        $this->application()->instance(CombinedGraphBuilder::class, $combinedBuilder);
        $this->application()->instance(
            ModuleInspectionBuilder::class,
            new ModuleInspectionBuilder(
                $registry,
                $compiler,
                $combinedBuilder,
                $sourceIndexBuilder,
                $publicApi,
                $resolver->resolve($configuration),
            ),
        );
    }
}

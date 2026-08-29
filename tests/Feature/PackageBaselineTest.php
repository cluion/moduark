<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Analysis\ArchitectureCheck;
use Cluion\Moduark\Analysis\ArchitectureChecker;
use Cluion\Moduark\Analysis\Baseline\ArchitectureBaselineStore;
use Cluion\Moduark\Analysis\Boundary\PublicApi;
use Cluion\Moduark\Analysis\RuleRunner;
use Cluion\Moduark\Analysis\RawArchitectureCheck;
use Cluion\Moduark\Analysis\Source\SourceAnalysisCacheStore;
use Cluion\Moduark\Analysis\Source\SourceIndexBuilder;
use Cluion\Moduark\Analysis\Suppression\SuppressionManifestStore;
use Cluion\Moduark\Analysis\UnbaselinedArchitectureCheck;
use Cluion\Moduark\Architecture\EffectiveArchitecture;
use Cluion\Moduark\Architecture\Level;
use Cluion\Moduark\Architecture\RulePresets;
use Cluion\Moduark\Architecture\RuleResolver;
use Cluion\Moduark\Capabilities\CapabilityResolver;
use Cluion\Moduark\Cache\ModuleCacheBuilder;
use Cluion\Moduark\Cache\ModuleCacheStore;
use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Discovery\ModuleDiscoverer;
use Cluion\Moduark\Graph\CapabilityGraphBuilder;
use Cluion\Moduark\Graph\CombinedGraphBuilder;
use Cluion\Moduark\Graph\Export\MermaidCapabilityGraphExporter;
use Cluion\Moduark\Graph\Export\MermaidCombinedGraphExporter;
use Cluion\Moduark\Graph\Export\MermaidModuleGraphExporter;
use Cluion\Moduark\Graph\Export\TextCapabilityGraphExporter;
use Cluion\Moduark\Graph\Export\TextCombinedGraphExporter;
use Cluion\Moduark\Graph\Export\TextModuleGraphExporter;
use Cluion\Moduark\Graph\ModuleGraphBuilder;
use Cluion\Moduark\Generation\GenerationPlanner;
use Cluion\Moduark\Generation\GenerationPlanValidator;
use Cluion\Moduark\Generation\GenerationPreflight;
use Cluion\Moduark\Generation\GenerationExecutor;
use Cluion\Moduark\Generation\GeneratorRegistry;
use Cluion\Moduark\Generation\ModuleMakerTargetResolver;
use Cluion\Moduark\Generation\ModuleScaffoldPlanner;
use Cluion\Moduark\Inspection\ModuleInspectionBuilder;
use Cluion\Moduark\Lifecycle\ModuleLifecycleRegistrar;
use Cluion\Moduark\Lifecycle\ModuleOrderer;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Persistence\TableOwnershipIndex;
use Cluion\Moduark\ModuarkServiceProvider;
use Cluion\Moduark\Package\ComposerPackageModuleDiscoverer;
use Cluion\Moduark\Registry\ModuleRegistry;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PackageBaselineTest extends TestCase
{
    public function test_package_and_workbench_providers_are_loaded(): void
    {
        $application = $this->application();

        self::assertArrayHasKey(ModuarkServiceProvider::class, $application->getLoadedProviders());
        self::assertTrue($application->bound(ModulesConfig::class));
        self::assertTrue($application->bound(RulePresets::class));
        self::assertTrue($application->bound(RuleResolver::class));
        self::assertTrue($application->bound(EffectiveArchitecture::class));
        self::assertTrue($application->bound(RuleRunner::class));
        self::assertTrue($application->bound(ArchitectureCheck::class));
        self::assertTrue($application->bound(ArchitectureChecker::class));
        self::assertTrue($application->bound(RawArchitectureCheck::class));
        self::assertTrue($application->bound(UnbaselinedArchitectureCheck::class));
        self::assertTrue($application->bound(ArchitectureBaselineStore::class));
        self::assertTrue($application->bound(SuppressionManifestStore::class));
        self::assertTrue($application->bound(PublicApi::class));
        self::assertTrue($application->bound(SourceAnalysisCacheStore::class));
        self::assertTrue($application->bound(SourceIndexBuilder::class));
        self::assertTrue($application->bound(ModuleDiscoverer::class));
        self::assertTrue($application->bound(ComposerPackageModuleDiscoverer::class));
        self::assertTrue($application->bound(ModuleRegistry::class));
        self::assertTrue($application->bound(ModuleMetadataCompiler::class));
        self::assertTrue($application->bound(TableOwnershipIndex::class));
        self::assertTrue($application->bound(ModuleCacheBuilder::class));
        self::assertTrue($application->bound(ModuleCacheStore::class));
        self::assertTrue($application->bound(CapabilityGraphBuilder::class));
        self::assertTrue($application->bound(CombinedGraphBuilder::class));
        self::assertTrue($application->bound(ModuleGraphBuilder::class));
        self::assertTrue($application->bound(TextCapabilityGraphExporter::class));
        self::assertTrue($application->bound(MermaidCapabilityGraphExporter::class));
        self::assertTrue($application->bound(TextCombinedGraphExporter::class));
        self::assertTrue($application->bound(MermaidCombinedGraphExporter::class));
        self::assertTrue($application->bound(TextModuleGraphExporter::class));
        self::assertTrue($application->bound(MermaidModuleGraphExporter::class));
        self::assertTrue($application->bound(ModuleInspectionBuilder::class));
        self::assertTrue($application->bound(GeneratorRegistry::class));
        self::assertTrue($application->bound(GenerationPlanner::class));
        self::assertTrue($application->bound(GenerationPlanValidator::class));
        self::assertTrue($application->bound(GenerationPreflight::class));
        self::assertTrue($application->bound(GenerationExecutor::class));
        self::assertTrue($application->bound(ModuleMakerTargetResolver::class));
        self::assertTrue($application->bound(ModuleScaffoldPlanner::class));
        self::assertTrue($application->bound(ModuleOrderer::class));
        self::assertTrue($application->bound(CapabilityResolver::class));
        self::assertTrue($application->bound(ModuleLifecycleRegistrar::class));
        self::assertSame(
            $application->make(CapabilityResolver::class),
            $application->make(CapabilityResolver::class),
        );
        self::assertSame(
            $application->make(TableOwnershipIndex::class),
            $application->make(TableOwnershipIndex::class),
        );
        self::assertTrue($application->bound('moduark.workbench.loaded'));
    }

    public function test_workbench_path_keeps_the_default_level_one(): void
    {
        $configuration = $this->application()->make(ModulesConfig::class);

        self::assertSame(dirname(__DIR__, 2).'/workbench/app/Modules', $configuration->path());
        self::assertSame(Level::Modular, $configuration->level());
        self::assertSame($this->application()->basePath('moduark-baseline.json'), $configuration->baselinePath());
        self::assertSame(
            $this->application()->basePath('moduark-suppressions.json'),
            $configuration->suppressionPath(),
        );
        self::assertSame(1, config('moduark.architecture.level'));
    }

    public function test_config_publish_uses_the_package_owned_filename(): void
    {
        self::assertSame([
            dirname(__DIR__, 2).'/config/moduark.php' => $this->application()->configPath('moduark.php'),
        ], ServiceProvider::pathsToPublish(ModuarkServiceProvider::class, 'moduark-config'));
    }

    public function test_configuration_survives_config_cache(): void
    {
        $expected = $this->application()->make(EffectiveArchitecture::class)->toArray();

        try {
            $this->command('config:cache')->assertSuccessful();
            $this->refreshApplication();

            $configuration = $this->application()->make(ModulesConfig::class);

            self::assertSame(Level::Modular, $configuration->level());
            self::assertSame(dirname(__DIR__, 2).'/workbench/app/Modules', $configuration->path());
            self::assertSame(
                $this->application()->basePath('moduark-baseline.json'),
                $configuration->baselinePath(),
            );
            self::assertSame(
                $this->application()->basePath('moduark-suppressions.json'),
                $configuration->suppressionPath(),
            );
            self::assertSame(
                $expected,
                $this->application()->make(EffectiveArchitecture::class)->toArray(),
            );
        } finally {
            $this->command('config:clear')->run();
        }
    }

    #[DataProvider('frameworkCacheCommands')]
    public function test_descriptor_payload_survives_framework_cache_commands(
        string $cacheCommand,
        string $clearCommand,
    ): void {
        $expected = $this->descriptorPayload();

        try {
            $this->command($cacheCommand)->assertSuccessful();
            $this->refreshApplication();

            $actual = $this->descriptorPayload();

            self::assertSame($expected, $actual);

            array_walk_recursive($actual, static function (mixed $value): void {
                self::assertTrue(is_scalar($value) || $value === null);
            });
        } finally {
            $this->command($clearCommand)->run();
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function frameworkCacheCommands(): iterable
    {
        yield 'config cache' => ['config:cache', 'config:clear'];
        yield 'optimize cache' => ['optimize', 'optimize:clear'];
    }

    /**
     * @return array<mixed>
     */
    private function descriptorPayload(): array
    {
        $configuration = $this->application()->make(Repository::class);
        $payload = $configuration->get('moduark.workbench.descriptors');

        if (! is_array($payload)) {
            throw new LogicException('The workbench descriptor payload is not available.');
        }

        return $payload;
    }
}

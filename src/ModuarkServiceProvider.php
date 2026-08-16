<?php

declare(strict_types=1);

namespace Cluion\Moduark;

use Cluion\Moduark\Analysis\ArchitectureCheck;
use Cluion\Moduark\Analysis\ArchitectureChecker;
use Cluion\Moduark\Analysis\Baseline\ArchitectureBaselineStore;
use Cluion\Moduark\Analysis\Baseline\BaselineArchitectureCheck;
use Cluion\Moduark\Analysis\Boundary\ConventionPublicApi;
use Cluion\Moduark\Analysis\Boundary\PublicApi;
use Cluion\Moduark\Analysis\Export\GithubCheckReportExporter;
use Cluion\Moduark\Analysis\Export\JsonCheckReportExporter;
use Cluion\Moduark\Analysis\RuleRunner;
use Cluion\Moduark\Analysis\RawArchitectureCheck;
use Cluion\Moduark\Analysis\Rules\AdapterBoundariesRule;
use Cluion\Moduark\Analysis\Rules\CapabilityContractsRule;
use Cluion\Moduark\Analysis\Rules\CyclesRule;
use Cluion\Moduark\Analysis\Rules\InternalApiAccessRule;
use Cluion\Moduark\Analysis\Rules\MissingDependenciesRule;
use Cluion\Moduark\Analysis\Rules\UndeclaredDependenciesRule;
use Cluion\Moduark\Analysis\Rules\UniqueModuleIdentityRule;
use Cluion\Moduark\Analysis\Rules\ValidModuleStructureRule;
use Cluion\Moduark\Analysis\Source\SourceAnalysisCacheStore;
use Cluion\Moduark\Analysis\Source\SourceIndexBuilder;
use Cluion\Moduark\Analysis\Suppression\SuppressionArchitectureCheck;
use Cluion\Moduark\Analysis\Suppression\SuppressionManifestStore;
use Cluion\Moduark\Analysis\UnbaselinedArchitectureCheck;
use Cluion\Moduark\Architecture\EffectiveArchitecture;
use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Architecture\RulePresets;
use Cluion\Moduark\Architecture\RuleResolver;
use Cluion\Moduark\Capabilities\CapabilityResolver;
use Cluion\Moduark\Cache\ModuleCacheBuilder;
use Cluion\Moduark\Cache\ModuleCacheStore;
use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Console\MakeModuleCommand;
use Cluion\Moduark\Console\ModuleBaselineCommand;
use Cluion\Moduark\Console\ModuleCacheCommand;
use Cluion\Moduark\Console\ModuleCheckCommand;
use Cluion\Moduark\Console\ModuleClearCommand;
use Cluion\Moduark\Console\ModuleGraphCommand;
use Cluion\Moduark\Console\ModuleInspectCommand;
use Cluion\Moduark\Console\ModuleListCommand;
use Cluion\Moduark\Console\ModuleMakeCommand;
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
use Cluion\Moduark\Generation\ModuleMakerTargetResolver;
use Cluion\Moduark\Inspection\ModuleInspectionBuilder;
use Cluion\Moduark\Lifecycle\ModuleLifecycleRegistrar;
use Cluion\Moduark\Lifecycle\ModuleOrderer;
use Cluion\Moduark\Lifecycle\OrderedModules;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Registry\ModuleRegistry;
use Cluion\Moduark\Resources\ModuleResourceDiscoverer;
use Cluion\Moduark\Resources\ModuleResourceServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class ModuarkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $path = dirname(__DIR__).'/config/modules.php';
        $defaults = require $path;

        if (! is_array($defaults)) {
            throw new InvalidArgumentException('The Moduark default configuration must return an array.');
        }

        $this->mergeConfigFrom($path, 'modules');

        /** @var Repository $repository */
        $repository = $this->app->make(Repository::class);
        $configured = $repository->get('modules', []);

        if (! is_array($configured)) {
            throw new InvalidArgumentException('The modules configuration must be an array.');
        }

        $configuration = ModulesConfig::from($defaults, $configured);

        $repository->set('modules', $configuration->all());
        $this->app->instance(ModulesConfig::class, $configuration);
        $this->app->singleton(RulePresets::class);
        $this->app->singleton(RuleResolver::class);
        $this->app->singleton(PublicApi::class, ConventionPublicApi::class);
        $this->app->instance(
            EffectiveArchitecture::class,
            $this->app->make(RuleResolver::class)->resolve($configuration),
        );
        $this->app->singleton(ModuleDiscoverer::class);
        $this->app->singleton(
            ModuleCacheStore::class,
            fn (): ModuleCacheStore => new ModuleCacheStore($this->app->bootstrapPath('cache/moduark.php')),
        );
        $manifest = $this->app->make(ModuleCacheStore::class)->load($configuration->path());

        if ($manifest === null) {
            $this->app->singleton(
                ModuleRegistry::class,
                fn (): ModuleRegistry => $this->app->make(ModuleDiscoverer::class)
                    ->discover($this->app->make(ModulesConfig::class)->path()),
            );
            $this->app->singleton(ModuleMetadataCompiler::class);
        } else {
            $this->app->instance(ModuleRegistry::class, $manifest->registry());
            $this->app->instance(
                ModuleMetadataCompiler::class,
                new ModuleMetadataCompiler($manifest->descriptors()),
            );
        }
        $this->app->singleton(
            SourceAnalysisCacheStore::class,
            fn (): SourceAnalysisCacheStore => new SourceAnalysisCacheStore(
                $this->app->bootstrapPath('cache/moduark-analysis.php'),
            ),
        );
        $this->app->singleton(
            SourceIndexBuilder::class,
            fn (): SourceIndexBuilder => new SourceIndexBuilder(
                $this->app->make(ModuleRegistry::class),
                $this->app->make(SourceAnalysisCacheStore::class),
            ),
        );
        $this->app->singleton(GithubCheckReportExporter::class);
        $this->app->singleton(JsonCheckReportExporter::class);
        $this->app->singleton(ArchitectureBaselineStore::class);
        $this->app->singleton(SuppressionManifestStore::class);
        $this->app->singleton(CapabilityGraphBuilder::class);
        $this->app->singleton(CombinedGraphBuilder::class);
        $this->app->singleton(ModuleGraphBuilder::class);
        $this->app->singleton(TextCapabilityGraphExporter::class);
        $this->app->singleton(MermaidCapabilityGraphExporter::class);
        $this->app->singleton(TextCombinedGraphExporter::class);
        $this->app->singleton(MermaidCombinedGraphExporter::class);
        $this->app->singleton(TextModuleGraphExporter::class);
        $this->app->singleton(MermaidModuleGraphExporter::class);
        $this->app->singleton(ModuleInspectionBuilder::class);
        $this->app->singleton(ModuleMakerTargetResolver::class);
        $this->app->singleton(ModuleOrderer::class);
        $this->app->singleton(CapabilityResolver::class);
        $this->app->singleton(ModuleCacheBuilder::class);
        $this->app->singleton(ModuleLifecycleRegistrar::class);
        $this->app->singleton(ModuleResourceDiscoverer::class);
        $this->app->singleton(ExitPolicy::class);
        $this->app->singleton(
            RuleRunner::class,
            fn (): RuleRunner => new RuleRunner([
                new ValidModuleStructureRule,
                new UniqueModuleIdentityRule,
                new MissingDependenciesRule,
                new UndeclaredDependenciesRule,
                new CyclesRule,
                new InternalApiAccessRule($this->app->make(PublicApi::class)),
                new CapabilityContractsRule,
                new AdapterBoundariesRule,
            ]),
        );
        $this->app->singleton(ArchitectureChecker::class);
        $this->app->singleton(
            RawArchitectureCheck::class,
            fn (): RawArchitectureCheck => $this->app->make(ArchitectureChecker::class),
        );
        $this->app->singleton(
            UnbaselinedArchitectureCheck::class,
            fn (): UnbaselinedArchitectureCheck => new SuppressionArchitectureCheck(
                $this->app->make(RawArchitectureCheck::class),
                $this->app->make(SuppressionManifestStore::class),
                $this->app->make(ModulesConfig::class),
                $this->app->basePath(),
            ),
        );
        $this->app->singleton(
            ArchitectureCheck::class,
            fn (): ArchitectureCheck => new BaselineArchitectureCheck(
                $this->app->make(UnbaselinedArchitectureCheck::class),
                $this->app->make(ArchitectureBaselineStore::class),
                $this->app->make(ModulesConfig::class),
                $this->app->basePath(),
            ),
        );

        $registry = $this->app->make(ModuleRegistry::class);
        $ordered = $this->app->make(ModuleLifecycleRegistrar::class)
            ->registerProviders($registry->moduleClasses());

        $this->app->instance(OrderedModules::class, new OrderedModules($ordered));
        $this->app->register(ModuleResourceServiceProvider::class);
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            MakeModuleCommand::class,
            ModuleBaselineCommand::class,
            ModuleCacheCommand::class,
            ModuleCheckCommand::class,
            ModuleClearCommand::class,
            ModuleGraphCommand::class,
            ModuleInspectCommand::class,
            ModuleListCommand::class,
            ModuleMakeCommand::class,
        ]);

        $this->optimizes('module:cache', 'module:clear');

        $this->publishes([
            dirname(__DIR__).'/config/modules.php' => config_path('modules.php'),
        ], 'moduark-config');

        $this->publishes([
            dirname(__DIR__).'/stubs/module.stub' => base_path('stubs/module.stub'),
        ], 'moduark-stubs');
    }
}

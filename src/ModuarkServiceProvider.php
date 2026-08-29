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
use Cluion\Moduark\Analysis\Rules\CrossModuleForeignKeysRule;
use Cluion\Moduark\Analysis\Rules\CrossModuleModelAccessRule;
use Cluion\Moduark\Analysis\Rules\CrossModuleTransactionsRule;
use Cluion\Moduark\Analysis\Rules\DatabaseOwnershipRule;
use Cluion\Moduark\Analysis\Rules\CyclesRule;
use Cluion\Moduark\Analysis\Rules\ExplicitPublicExportsRule;
use Cluion\Moduark\Analysis\Rules\InternalApiAccessRule;
use Cluion\Moduark\Analysis\Rules\MigrationOwnershipRule;
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
use Cluion\Moduark\Console\ModuleDisableCommand;
use Cluion\Moduark\Console\ModuleGraphCommand;
use Cluion\Moduark\Console\ModuleDoctorCommand;
use Cluion\Moduark\Console\ModuleEnableCommand;
use Cluion\Moduark\Console\ModuleInspectCommand;
use Cluion\Moduark\Console\ModuleListCommand;
use Cluion\Moduark\Console\ModuleMakeCommand;
use Cluion\Moduark\Console\ModuleMigrateCommand;
use Cluion\Moduark\Console\ModuleResourcesCommand;
use Cluion\Moduark\Console\ModuleSeedCommand;
use Cluion\Moduark\Console\ModuleTestCommand;
use Cluion\Moduark\Discovery\ModuleActivationSet;
use Cluion\Moduark\Discovery\ModuleDiscoverer;
use Cluion\Moduark\Discovery\NwidartModuleActivationResolver;
use Cluion\Moduark\Extraction\ArchitectureExtractabilityGate;
use Cluion\Moduark\Extraction\ExtractabilityInspector;
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
use Cluion\Moduark\Generation\GenerationPreflight;
use Cluion\Moduark\Generation\GenerationExecutor;
use Cluion\Moduark\Generation\GenerationPlanValidator;
use Cluion\Moduark\Generation\GeneratorRegistration;
use Cluion\Moduark\Generation\GeneratorRegistry;
use Cluion\Moduark\Generation\ModuleMakerTargetResolver;
use Cluion\Moduark\Generation\ModuleMakerType;
use Cluion\Moduark\Generation\ModuleScaffoldPlanner;
use Cluion\Moduark\Inspection\ModuleInspectionBuilder;
use Cluion\Moduark\Lifecycle\ModuleLifecycleRegistrar;
use Cluion\Moduark\Lifecycle\ModuleOrderer;
use Cluion\Moduark\Lifecycle\OrderedModules;
use Cluion\Moduark\Lifecycle\Activation\ApplicationModuleActivationCacheInvalidator;
use Cluion\Moduark\Lifecycle\Activation\AtomicFileWriter;
use Cluion\Moduark\Lifecycle\Activation\FileModuleActivationStore;
use Cluion\Moduark\Lifecycle\Activation\ModuleActivationDriver;
use Cluion\Moduark\Lifecycle\Activation\ModuleActivationCacheInvalidator;
use Cluion\Moduark\Lifecycle\Activation\ModuleActivationMutator;
use Cluion\Moduark\Lifecycle\Activation\ModuleActivationPlanner;
use Cluion\Moduark\Lifecycle\Activation\ModuleActivationState;
use Cluion\Moduark\Lifecycle\Activation\NativeAtomicFileWriter;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Persistence\TableOwnershipIndex;
use Cluion\Moduark\Registry\ModuleRegistry;
use Cluion\Moduark\Resources\ModuleResourceDiscoverer;
use Cluion\Moduark\Resources\ModuleResourceServiceProvider;
use Cluion\Moduark\Resources\BuiltInResourcePlugins;
use Cluion\Moduark\Resources\ResourceManifest;
use Cluion\Moduark\Resources\ResourceManifestBuilder;
use Cluion\Moduark\Resources\ResourceManifestStatus;
use Cluion\Moduark\Resources\ResourceOwnership;
use Cluion\Moduark\Resources\ResourcePluginRegistry;
use Cluion\Moduark\Resources\ResourceInspector;
use Cluion\Moduark\Resources\ModuleOperationResolver;
use Cluion\Moduark\Resources\ModuleAssetManifest;
use Cluion\Moduark\Resources\ResourceRegistrationState;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use RuntimeException;

final class ModuarkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $path = dirname(__DIR__).'/config/moduark.php';
        $defaults = require $path;

        if (! is_array($defaults)) {
            throw new InvalidArgumentException('The Moduark default configuration must return an array.');
        }

        /** @var Repository $repository */
        $repository = $this->app->make(Repository::class);
        $applicationConfiguration = $repository->get('moduark', []);

        if (! is_array($applicationConfiguration)) {
            throw new InvalidArgumentException('The moduark configuration must be an array.');
        }

        $this->mergeConfigFrom($path, 'moduark');

        $configured = $repository->get('moduark', []);

        if (! is_array($configured)) {
            throw new InvalidArgumentException('The moduark configuration must be an array.');
        }

        $nwidartPath = $this->nwidartModulesPath($repository);

        if (
            ! array_key_exists('path', $applicationConfiguration)
            || $applicationConfiguration['path'] === null
        ) {
            $configured['path'] = $nwidartPath ?? app_path('Modules');
        }

        $configuration = ModulesConfig::from($defaults, $configured);
        $followsNwidart = $nwidartPath !== null
            && $this->pathsMatch($configuration->path(), $nwidartPath);

        $repository->set('moduark', $configuration->all());
        $this->app->instance(ModulesConfig::class, $configuration);
        $atomicWriter = new NativeAtomicFileWriter;
        $this->app->instance(AtomicFileWriter::class, $atomicWriter);
        $activationState = $followsNwidart
            ? $this->nwidartActivationState($repository, $configuration->path(), $atomicWriter)
            : $this->standaloneActivationState($configuration, $atomicWriter);
        $activationSet = $activationState->activationSet();
        $this->app->instance(ModuleActivationSet::class, $activationSet);
        $this->app->instance(ModuleActivationState::class, $activationState);
        $this->app->instance(ResourceOwnership::class, new ResourceOwnership($followsNwidart));
        $this->app->singleton(RulePresets::class);
        $this->app->singleton(RuleResolver::class);
        $this->app->singleton(PublicApi::class, ConventionPublicApi::class);
        $this->app->instance(
            EffectiveArchitecture::class,
            $this->app->make(RuleResolver::class)->resolve($configuration),
        );
        $this->app->singleton(ModuleDiscoverer::class);
        $resourcePlugins = new ResourcePluginRegistry;
        BuiltInResourcePlugins::register($resourcePlugins);
        $this->app->instance(ResourcePluginRegistry::class, $resourcePlugins);
        $this->app->singleton(ResourceManifestBuilder::class);
        $this->app->singleton(
            ModuleCacheStore::class,
            fn (): ModuleCacheStore => new ModuleCacheStore($this->app->bootstrapPath('cache/moduark.php')),
        );
        $manifest = $this->app->make(ModuleCacheStore::class)->load(
            $configuration->path(),
            $activationSet->fingerprint(),
        );

        if ($manifest === null) {
            $this->app->singleton(
                ModuleRegistry::class,
                fn (): ModuleRegistry => $this->app->make(ModuleDiscoverer::class)
                    ->discover(
                        $this->app->make(ModulesConfig::class)->path(),
                        $this->app->make(ModuleActivationSet::class),
                    ),
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
            ModuleActivationCacheInvalidator::class,
            ApplicationModuleActivationCacheInvalidator::class,
        );
        $this->app->singleton(ModuleActivationMutator::class);
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
        $this->app->singleton(
            ExtractabilityInspector::class,
            fn (): ExtractabilityInspector => new ExtractabilityInspector(
                $this->app->make(ModuleRegistry::class),
                $this->app->make(ModuleMetadataCompiler::class),
                $this->app->make(ResourceManifest::class),
                $this->app->make(ModulesConfig::class),
                $this->app->basePath('vendor'),
                $this->app->make(ArchitectureExtractabilityGate::class),
            ),
        );
        $this->app->singleton(ArchitectureExtractabilityGate::class);
        $this->app->singleton(GeneratorRegistry::class);

        foreach (ModuleMakerType::cases() as $descriptor) {
            GeneratorRegistration::register($this->app, $descriptor);
        }

        $this->app->singleton(GenerationPlanner::class);
        $this->app->singleton(GenerationPlanValidator::class);
        $this->app->singleton(GenerationPreflight::class);
        $this->app->singleton(GenerationExecutor::class);
        $this->app->singleton(ModuleMakerTargetResolver::class);
        $this->app->singleton(ModuleScaffoldPlanner::class);
        $this->app->singleton(ModuleOrderer::class);
        $this->app->singleton(CapabilityResolver::class);
        $this->app->singleton(
            ModuleActivationPlanner::class,
            fn (): ModuleActivationPlanner => new ModuleActivationPlanner(
                new ModuleMetadataCompiler,
                $this->app->make(ModuleOrderer::class),
                $this->app->make(CapabilityResolver::class),
            ),
        );
        $this->app->singleton(
            TableOwnershipIndex::class,
            fn (): TableOwnershipIndex => new TableOwnershipIndex(
                $this->app->make(ModuleMetadataCompiler::class)->compileAll(
                    $this->app->make(ModuleRegistry::class)->moduleClasses(),
                ),
            ),
        );
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
                new CrossModuleModelAccessRule,
                new DatabaseOwnershipRule,
                new MigrationOwnershipRule,
                new CrossModuleForeignKeysRule,
                new CrossModuleTransactionsRule,
                new ExplicitPublicExportsRule,
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
        if ($manifest === null) {
            $this->app->singleton(
                ResourceManifest::class,
                fn (): ResourceManifest => $this->app->make(ResourceManifestBuilder::class)
                    ->build($registry, $ordered),
            );
        } else {
            $this->app->instance(ResourceManifest::class, $manifest->resources());
        }
        $this->app->instance(ResourceManifestStatus::class, new ResourceManifestStatus($manifest !== null));
        $this->app->singleton(ResourceInspector::class);
        $this->app->singleton(ModuleOperationResolver::class);
        $this->app->singleton(ModuleAssetManifest::class);
        $this->app->singleton(ResourceRegistrationState::class);
        $this->app->booting(function (): void {
            $this->app->register(ModuleResourceServiceProvider::class);
        });
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
            ModuleDisableCommand::class,
            ModuleDoctorCommand::class,
            ModuleEnableCommand::class,
            ModuleGraphCommand::class,
            ModuleInspectCommand::class,
            ModuleListCommand::class,
            ModuleMakeCommand::class,
            ModuleMigrateCommand::class,
            ModuleResourcesCommand::class,
            ModuleSeedCommand::class,
            ModuleTestCommand::class,
        ]);

        $this->optimizes('moduark:cache', 'moduark:clear');

        $this->publishes([
            dirname(__DIR__).'/config/moduark.php' => config_path('moduark.php'),
        ], 'moduark-config');

        $this->publishes([
            dirname(__DIR__).'/stubs/module.stub' => base_path('stubs/module.stub'),
        ], 'moduark-stubs');
    }

    private function nwidartModulesPath(Repository $repository): ?string
    {
        if (! class_exists('Nwidart\\Modules\\LaravelModulesServiceProvider')) {
            return null;
        }

        $path = $repository->get('modules.paths.modules', $this->app->basePath('Modules'));

        return is_string($path) && $path !== '' ? $path : null;
    }

    private function pathsMatch(string $left, string $right): bool
    {
        $resolvedLeft = realpath($left);
        $resolvedRight = realpath($right);

        if ($resolvedLeft !== false && $resolvedRight !== false) {
            return $resolvedLeft === $resolvedRight;
        }

        return rtrim(str_replace('\\', '/', $left), '/')
            === rtrim(str_replace('\\', '/', $right), '/');
    }

    private function standaloneActivationState(
        ModulesConfig $configuration,
        AtomicFileWriter $writer,
    ): ModuleActivationState {
        return $this->fileActivationState(
            ModuleActivationDriver::Standalone,
            $configuration->activationPath(),
            $configuration->path(),
            $writer,
        );
    }

    private function nwidartActivationState(
        Repository $repository,
        string $modulesPath,
        AtomicFileWriter $writer,
    ): ModuleActivationState {
        $resolver = new NwidartModuleActivationResolver;
        $activator = $repository->get('modules.activator');

        if ($activator === null) {
            return $this->fileActivationState(
                ModuleActivationDriver::Nwidart,
                $this->app->basePath('modules_statuses.json'),
                $modulesPath,
                $writer,
            );
        }

        if (! is_string($activator) || $activator === '') {
            throw new RuntimeException('The nwidart Module activator is not configured.');
        }

        $configuration = $repository->get('modules.activators.'.$activator);

        if (! is_array($configuration)) {
            if ($activator === 'file') {
                return $this->fileActivationState(
                    ModuleActivationDriver::Nwidart,
                    $this->app->basePath('modules_statuses.json'),
                    $modulesPath,
                    $writer,
                );
            }

            throw new RuntimeException("The nwidart Module activator [{$activator}] is invalid.");
        }

        $class = $configuration['class'] ?? null;

        if ($activator === 'file'
            && ($class === null || $class === 'Nwidart\\Modules\\Activators\\FileActivator')) {
            $statusesPath = $configuration['statuses-file'] ?? null;

            return $this->fileActivationState(
                ModuleActivationDriver::Nwidart,
                is_string($statusesPath) && $statusesPath !== ''
                    ? $statusesPath
                    : $this->app->basePath('modules_statuses.json'),
                $modulesPath,
                $writer,
            );
        }

        if (! is_string($class) || $class === '' || ! class_exists($class)) {
            throw new RuntimeException("The nwidart Module activator [{$activator}] is invalid.");
        }

        $instance = new $class($this->app);

        return new ModuleActivationState(
            ModuleActivationDriver::Nwidart,
            $resolver->resolve($instance, $modulesPath),
        );
    }

    private function fileActivationState(
        ModuleActivationDriver $driver,
        string $statePath,
        string $modulesPath,
        AtomicFileWriter $writer,
    ): ModuleActivationState {
        $store = new FileModuleActivationStore(
            $statePath,
            $this->app->bootstrapPath('cache/moduark-activation.lock'),
            $driver,
            $writer,
        );

        return new ModuleActivationState(
            $driver,
            $store->load($this->moduleDirectoryNames($modulesPath)),
            $store,
        );
    }

    /** @return list<string> */
    private function moduleDirectoryNames(string $modulesPath): array
    {
        if (! is_dir($modulesPath)) {
            return [];
        }

        $directories = glob(
            rtrim($modulesPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*',
            GLOB_ONLYDIR,
        );

        if ($directories === false) {
            throw new RuntimeException("Unable to scan Module path [{$modulesPath}].");
        }

        $names = array_map(static fn (string $directory): string => basename($directory), $directories);
        usort($names, static fn (string $left, string $right): int => strcasecmp($left, $right) ?: strcmp($left, $right));

        return $names;
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Lifecycle\Activation;

use Cluion\Moduark\Capabilities\CapabilityResolutionReason;
use Cluion\Moduark\Capabilities\CapabilityResolver;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Discovery\ModuleActivationSet;
use Cluion\Moduark\Exceptions\CapabilityResolutionFailed;
use Cluion\Moduark\Exceptions\CircularModuleDependency;
use Cluion\Moduark\Exceptions\InvalidModuleMetadata;
use Cluion\Moduark\Exceptions\ModuleActivationFailed;
use Cluion\Moduark\Lifecycle\ModuleOrderer;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Registry\ModuleRegistry;

final readonly class ModuleActivationPlanner
{
    public function __construct(
        private ModuleMetadataCompiler $compiler,
        private ModuleOrderer $orderer,
        private CapabilityResolver $capabilityResolver,
    ) {
    }

    /**
     * The inventory must contain every discovered Module, including disabled
     * Modules. Runtime active-only registries are not a valid planning input.
     *
     * @param list<class-string<\Cluion\Moduark\Module>> $alwaysActiveModuleClasses
     */
    public function plan(
        ModuleRegistry $inventory,
        ModuleActivationSet $current,
        string $module,
        ModuleActivationIntent $intent,
        array $alwaysActiveModuleClasses = [],
    ): ModuleActivationPlan {
        $target = $inventory->find($module);

        if ($target === null) {
            throw ModuleActivationFailed::unknownModule($module);
        }

        $before = $this->activeNames($inventory, $current);
        $after = $this->apply($before, $target->name(), $intent);
        $noOp = $before === $after;
        $proposed = $this->activationSet($inventory, $after);
        $fingerprint = $noOp ? $current->fingerprint() : $proposed->fingerprint();

        try {
            $descriptors = $this->compiler->compileAll(array_values(array_unique([
                ...$this->activeClasses($inventory, $proposed),
                ...$alwaysActiveModuleClasses,
            ])));
        } catch (InvalidModuleMetadata $exception) {
            return $this->blockedPlan(
                $target->name(),
                $intent,
                $noOp,
                $before,
                $after,
                $fingerprint,
                new ModuleActivationBlocker(
                    ModuleActivationBlockerCode::InvalidMetadata,
                    $exception->getMessage(),
                ),
            );
        }

        $missingDependencies = $this->missingDependencies($descriptors);

        if ($missingDependencies !== []) {
            return new ModuleActivationPlan(
                $target->name(),
                $intent,
                $noOp,
                $before,
                $after,
                [],
                $fingerprint,
                $missingDependencies,
            );
        }

        try {
            $ordered = $this->orderer->order($descriptors);
        } catch (CircularModuleDependency $exception) {
            return $this->blockedPlan(
                $target->name(),
                $intent,
                $noOp,
                $before,
                $after,
                $fingerprint,
                new ModuleActivationBlocker(
                    ModuleActivationBlockerCode::CircularDependency,
                    $exception->getMessage(),
                    ['cycle' => $exception->cycle()],
                ),
            );
        } catch (InvalidModuleMetadata $exception) {
            return $this->blockedPlan(
                $target->name(),
                $intent,
                $noOp,
                $before,
                $after,
                $fingerprint,
                new ModuleActivationBlocker(
                    ModuleActivationBlockerCode::InvalidMetadata,
                    $exception->getMessage(),
                ),
            );
        }

        try {
            $this->capabilityResolver->resolve($ordered);
        } catch (CapabilityResolutionFailed $exception) {
            return $this->blockedPlan(
                $target->name(),
                $intent,
                $noOp,
                $before,
                $after,
                $fingerprint,
                new ModuleActivationBlocker(
                    $this->capabilityBlockerCode($exception->reason()),
                    $exception->getMessage(),
                    $exception->context(),
                ),
            );
        }

        return new ModuleActivationPlan(
            $target->name(),
            $intent,
            $noOp,
            $before,
            $after,
            array_map(
                static fn (ModuleDescriptor $descriptor): string => $descriptor->moduleClass(),
                $ordered,
            ),
            $fingerprint,
        );
    }

    /** @return list<string> */
    private function activeNames(ModuleRegistry $inventory, ModuleActivationSet $activationSet): array
    {
        return array_values(array_map(
            static fn (DiscoveredModule $module): string => $module->name(),
            array_filter(
                $inventory->all(),
                static fn (DiscoveredModule $module): bool => $activationSet->includes($module->name()),
            ),
        ));
    }

    /**
     * @param list<string> $before
     * @return list<string>
     */
    private function apply(array $before, string $module, ModuleActivationIntent $intent): array
    {
        $active = array_fill_keys($before, true);

        if ($intent === ModuleActivationIntent::Enable) {
            $active[$module] = true;
        } else {
            unset($active[$module]);
        }

        $after = array_keys($active);
        usort($after, static fn (string $left, string $right): int => strcasecmp($left, $right) ?: strcmp($left, $right));

        return $after;
    }

    /** @param list<string> $activeNames */
    private function activationSet(ModuleRegistry $inventory, array $activeNames): ModuleActivationSet
    {
        $active = array_fill_keys($activeNames, true);
        $states = [];

        foreach ($inventory->all() as $module) {
            $states[$module->name()] = isset($active[$module->name()]);
        }

        return ModuleActivationSet::fromStates($states);
    }

    /** @return list<class-string<\Cluion\Moduark\Module>> */
    private function activeClasses(ModuleRegistry $inventory, ModuleActivationSet $activationSet): array
    {
        return array_values(array_map(
            static fn (DiscoveredModule $module): string => $module->moduleClass(),
            array_filter(
                $inventory->all(),
                static fn (DiscoveredModule $module): bool => $activationSet->includes($module->name()),
            ),
        ));
    }

    /**
     * @param list<ModuleDescriptor> $descriptors
     * @return list<ModuleActivationBlocker>
     */
    private function missingDependencies(array $descriptors): array
    {
        $active = [];

        foreach ($descriptors as $descriptor) {
            $active[$descriptor->moduleClass()] = true;
        }

        $blockers = [];

        foreach ($descriptors as $descriptor) {
            foreach ($descriptor->dependencies() as $dependency) {
                if (isset($active[$dependency])) {
                    continue;
                }

                $module = $descriptor->moduleClass();
                $blockers[] = new ModuleActivationBlocker(
                    ModuleActivationBlockerCode::MissingDependency,
                    "Module [{$module}] depends on missing module [{$dependency}].",
                    [
                        'module' => $module,
                        'dependency' => $dependency,
                    ],
                );
            }
        }

        usort(
            $blockers,
            static fn (ModuleActivationBlocker $left, ModuleActivationBlocker $right): int =>
                $left->message() <=> $right->message(),
        );

        return $blockers;
    }

    /**
     * @param list<string> $before
     * @param list<string> $after
     */
    private function blockedPlan(
        string $module,
        ModuleActivationIntent $intent,
        bool $noOp,
        array $before,
        array $after,
        string $fingerprint,
        ModuleActivationBlocker $blocker,
    ): ModuleActivationPlan {
        return new ModuleActivationPlan(
            $module,
            $intent,
            $noOp,
            $before,
            $after,
            [],
            $fingerprint,
            [$blocker],
        );
    }

    private function capabilityBlockerCode(
        CapabilityResolutionReason $reason,
    ): ModuleActivationBlockerCode {
        return match ($reason) {
            CapabilityResolutionReason::MissingProvider => ModuleActivationBlockerCode::MissingCapabilityProvider,
            CapabilityResolutionReason::AmbiguousProvider => ModuleActivationBlockerCode::AmbiguousCapabilityProvider,
            CapabilityResolutionReason::DuplicatePort => ModuleActivationBlockerCode::DuplicateCapabilityPort,
        };
    }
}

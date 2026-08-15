<?php

declare(strict_types=1);

namespace Tests\Fixtures\LevelTwo\Support;

use Cluion\Moduark\Module;
use Illuminate\Foundation\Application;
use InvalidArgumentException;

final class CapabilityResolver
{
    /**
     * Resolve the complete Capability graph without mutating the container.
     *
     * @param list<class-string<Module>> $moduleClasses
     */
    public function resolve(array $moduleClasses): CapabilityPlan
    {
        /** @var array<class-string<Capability>, list<class-string<Module>>> $providers */
        $providers = [];

        /** @var list<array{consumer: class-string<Module>, requirement: CapabilityRequirement}> $requirements */
        $requirements = [];

        foreach ($moduleClasses as $moduleClass) {
            $module = new $moduleClass;

            if (! $module instanceof CapabilityMetadata) {
                throw new InvalidArgumentException(
                    "Capability owner [{$moduleClass}] must implement ".CapabilityMetadata::class.'.',
                );
            }

            foreach ($module->provides() as $capability) {
                $providers[$capability][] = $moduleClass;
            }

            foreach ($module->requires() as $requirement) {
                $requirements[] = [
                    'consumer' => $moduleClass,
                    'requirement' => $requirement,
                ];
            }
        }

        foreach ($providers as &$matches) {
            sort($matches, SORT_STRING);
        }
        unset($matches);

        usort($requirements, static function (array $left, array $right): int {
            return [
                $left['consumer'],
                $left['requirement']->capability(),
                $left['requirement']->port(),
            ] <=> [
                $right['consumer'],
                $right['requirement']->capability(),
                $right['requirement']->port(),
            ];
        });

        $bindings = [];

        foreach ($requirements as $candidate) {
            $requirement = $candidate['requirement'];
            $matches = $providers[$requirement->capability()] ?? [];

            if ($matches === []) {
                throw CapabilityResolutionFailed::missingProvider(
                    $requirement->capability(),
                    $candidate['consumer'],
                );
            }

            if (count($matches) > 1) {
                throw CapabilityResolutionFailed::ambiguousProvider(
                    $requirement->capability(),
                    $candidate['consumer'],
                    $matches,
                );
            }

            $bindings[] = new CapabilityBinding(
                $requirement->capability(),
                $matches[0],
                $candidate['consumer'],
                $requirement->port(),
                $requirement->adapter(),
            );
        }

        return new CapabilityPlan($bindings);
    }

    public function wire(Application $application, CapabilityPlan $plan): void
    {
        foreach ($plan->bindings() as $binding) {
            $application->bind($binding->port(), $binding->adapter());
        }
    }
}

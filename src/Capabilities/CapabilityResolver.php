<?php

declare(strict_types=1);

namespace Cluion\Moduark\Capabilities;

use Cluion\Moduark\Capability;
use Cluion\Moduark\CapabilityRequirement;
use Cluion\Moduark\Exceptions\CapabilityResolutionFailed;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Module;

final class CapabilityResolver
{
    /**
     * Resolve the complete Capability graph without invoking Modules or mutating
     * the Laravel container.
     *
     * @param list<ModuleDescriptor> $descriptors
     */
    public function resolve(array $descriptors): CapabilityPlan
    {
        /** @var array<class-string<Capability>, list<class-string<Module>>> $providers */
        $providers = [];

        /** @var list<array{consumer: class-string<Module>, requirement: CapabilityRequirement}> $requirements */
        $requirements = [];

        foreach ($descriptors as $descriptor) {
            foreach ($descriptor->provides() as $capability) {
                $providers[$capability][] = $descriptor->moduleClass();
            }

            foreach ($descriptor->requires() as $requirement) {
                $requirements[] = [
                    'consumer' => $descriptor->moduleClass(),
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

        /** @var array<class-string, class-string<Module>> $ports */
        $ports = [];

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

            $port = $requirement->port();

            if (isset($ports[$port])) {
                throw CapabilityResolutionFailed::duplicatePort(
                    $port,
                    $ports[$port],
                    $candidate['consumer'],
                );
            }

            $ports[$port] = $candidate['consumer'];

            $bindings[] = new CapabilityBinding(
                $requirement->capability(),
                $matches[0],
                $candidate['consumer'],
                $port,
                $requirement->adapter(),
            );
        }

        return new CapabilityPlan($bindings);
    }
}

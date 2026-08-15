<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Rules;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\ArchitectureRule;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Architecture\Violation;
use Cluion\Moduark\Capability;
use Cluion\Moduark\CapabilityRequirement;
use Cluion\Moduark\Module;

final class CapabilityContractsRule implements ArchitectureRule
{
    public function id(): RuleId
    {
        return RuleId::CapabilityContracts;
    }

    public function inspect(AnalysisContext $context, Severity $severity): RuleResult
    {
        /** @var array<class-string<Capability>, list<class-string<Module>>> $providers */
        $providers = [];

        /** @var list<array{consumer: class-string<Module>, requirement: CapabilityRequirement}> $requirements */
        $requirements = [];

        foreach ($context->descriptors() as $descriptor) {
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

        $violations = [];

        /** @var array<class-string, array<class-string<Module>, true>> $portConsumers */
        $portConsumers = [];

        foreach ($requirements as $candidate) {
            $consumerClass = $candidate['consumer'];
            $requirement = $candidate['requirement'];
            $capability = $requirement->capability();
            $matches = $providers[$capability] ?? [];
            $consumer = $context->module($consumerClass);
            $consumerName = $consumer?->name() ?? $context->displayName($consumerClass);
            $portConsumers[$requirement->port()][$consumerClass] = true;

            if ($matches === []) {
                $violations[] = new Violation(
                    $this->id(),
                    'MOD-CAPABILITY-001',
                    $severity,
                    "Module [{$consumerName}] requires Capability [{$capability}] with no provider.",
                    $consumer?->path(),
                    null,
                    $consumerName,
                    null,
                    $capability,
                    "Declare Capability [{$capability}] in exactly one discovered Module::provides().",
                );

                continue;
            }

            if (count($matches) > 1) {
                $providerNames = array_map(
                    static fn (string $provider): string => $context->displayName($provider),
                    $matches,
                );
                sort($providerNames, SORT_STRING);
                $target = implode(', ', $providerNames);

                $violations[] = new Violation(
                    $this->id(),
                    'MOD-CAPABILITY-002',
                    $severity,
                    "Module [{$consumerName}] requires Capability [{$capability}] provided by multiple Modules [{$target}].",
                    $consumer?->path(),
                    null,
                    $consumerName,
                    $target,
                    $capability,
                    "Leave exactly one discovered Module providing Capability [{$capability}].",
                );
            }
        }

        ksort($portConsumers, SORT_STRING);

        foreach ($portConsumers as $port => $consumers) {
            if (count($consumers) < 2) {
                continue;
            }

            $consumerClasses = array_keys($consumers);
            usort($consumerClasses, static fn (string $left, string $right): int => [
                $context->displayName($left),
                $left,
            ] <=> [
                $context->displayName($right),
                $right,
            ]);
            $consumerNames = array_map(
                static fn (string $consumer): string => $context->displayName($consumer),
                $consumerClasses,
            );
            $firstConsumer = $context->module($consumerClasses[0]);
            $firstName = $consumerNames[0];
            $targets = implode(', ', array_slice($consumerNames, 1));

            $violations[] = new Violation(
                $this->id(),
                'MOD-CAPABILITY-003',
                $severity,
                sprintf(
                    'Consumer Modules [%s] require the same Capability Port [%s].',
                    implode(', ', $consumerNames),
                    $port,
                ),
                $firstConsumer?->path(),
                null,
                $firstName,
                $targets,
                $port,
                'Give each consumer Module its own Port interface and CapabilityRequirement mapping.',
            );
        }

        usort($violations, static fn (Violation $left, Violation $right): int => [
            $left->code(),
            $left->consumer() ?? '',
            $left->target() ?? '',
            $left->symbol() ?? '',
        ] <=> [
            $right->code(),
            $right->consumer() ?? '',
            $right->target() ?? '',
            $right->symbol() ?? '',
        ]);

        return new RuleResult($this->id(), $violations);
    }
}

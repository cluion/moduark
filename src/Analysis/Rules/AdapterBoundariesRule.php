<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Rules;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\ArchitectureRule;
use Cluion\Moduark\Analysis\Source\SourceReference;
use Cluion\Moduark\Analysis\Source\SourceSymbol;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Architecture\Violation;
use Cluion\Moduark\Capability;
use Cluion\Moduark\CapabilityRequirement;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Module;

final class AdapterBoundariesRule implements ArchitectureRule
{
    public function id(): RuleId
    {
        return RuleId::AdapterBoundaries;
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
                $left['requirement']->adapter(),
            ] <=> [
                $right['consumer'],
                $right['requirement']->capability(),
                $right['requirement']->port(),
                $right['requirement']->adapter(),
            ];
        });

        $violations = [];

        /**
         * @var array<class-string<Module>, array<string, array{
         *     adapters: array<class-string, true>,
         *     providers: array<class-string<Module>, true>
         * }>> $adapterFiles
         */
        $adapterFiles = [];

        /** @var array<class-string<Module>, array<string, true>> $deferredAdapterFiles */
        $deferredAdapterFiles = [];

        /** @var array<class-string<Module>, array<string, string>> $declaredAdapters */
        $declaredAdapters = [];

        foreach ($requirements as $candidate) {
            $consumerClass = $candidate['consumer'];
            $requirement = $candidate['requirement'];
            $consumer = $context->module($consumerClass);

            if ($consumer === null) {
                continue;
            }

            $this->inspectPort(
                $context,
                $consumer,
                $requirement,
                $severity,
                $violations,
            );

            $matches = $providers[$requirement->capability()] ?? [];
            $provider = count($matches) === 1
                ? $context->module($matches[0])
                : null;
            $adapter = $context->sourceIndex()->symbol($requirement->adapter());

            if ($adapter !== null && $adapter->owner() === $consumerClass) {
                $declaredAdapters[$consumerClass][strtolower($requirement->adapter())]
                    = $this->normalize($adapter->file());
            }

            $validAdapter = $this->validAdapterLocation(
                $adapter,
                $consumer,
                $provider,
                $requirement,
                $severity,
                $violations,
            );

            if ($validAdapter === null) {
                continue;
            }

            $file = $this->normalize($validAdapter->file());

            if ($provider === null) {
                $deferredAdapterFiles[$consumerClass][$file] = true;

                continue;
            }

            $adapterFiles[$consumerClass][$file]['adapters'][$requirement->adapter()] = true;
            $adapterFiles[$consumerClass][$file]['providers'][$provider->moduleClass()] = true;
        }

        foreach ($context->sourceIndex()->references() as $reference) {
            if ($reference->source() === $reference->target()) {
                $this->inspectConcreteAdapterReference(
                    $context,
                    $reference,
                    $declaredAdapters,
                    $severity,
                    $violations,
                );

                continue;
            }

            if ($this->isModuleDependencyReference($context, $reference)) {
                continue;
            }

            $sourceFile = $this->normalize($reference->file());
            $adapter = $adapterFiles[$reference->source()][$sourceFile] ?? null;

            if ($adapter !== null && isset($adapter['providers'][$reference->target()])) {
                continue;
            }

            if ($adapter !== null) {
                $this->addWrongProviderViolation(
                    $context,
                    $reference,
                    $adapter,
                    $severity,
                    $violations,
                );

                continue;
            }

            if (isset($deferredAdapterFiles[$reference->source()][$sourceFile])) {
                continue;
            }

            $consumer = $context->displayName($reference->source());
            $target = $context->displayName($reference->target());

            $violations[] = new Violation(
                $this->id(),
                'MOD-ADAPTER-003',
                $severity,
                "Module [{$consumer}] references Module [{$target}] outside a declared Capability Adapter.",
                $reference->file(),
                $reference->line(),
                $consumer,
                $target,
                $reference->symbol(),
                "Move the integration behind a consumer-owned Port and declare its Adapter in {$consumer}Module::requires().",
            );
        }

        usort($violations, static fn (Violation $left, Violation $right): int => [
            $left->code(),
            $left->file() ?? '',
            $left->line() ?? 0,
            $left->consumer() ?? '',
            $left->target() ?? '',
            $left->symbol() ?? '',
        ] <=> [
            $right->code(),
            $right->file() ?? '',
            $right->line() ?? 0,
            $right->consumer() ?? '',
            $right->target() ?? '',
            $right->symbol() ?? '',
        ]);

        return new RuleResult($this->id(), $violations);
    }

    /**
     * @param list<Violation> $violations
     */
    private function inspectPort(
        AnalysisContext $context,
        DiscoveredModule $consumer,
        CapabilityRequirement $requirement,
        Severity $severity,
        array &$violations,
    ): void {
        $port = $context->sourceIndex()->symbol($requirement->port());

        if ($port !== null
            && $port->owner() === $consumer->moduleClass()
            && $this->isBelow($port, $consumer, ['Ports'])) {
            return;
        }

        $violations[] = new Violation(
            $this->id(),
            'MOD-ADAPTER-001',
            $severity,
            "Capability Port [{$requirement->port()}] must be owned by Module [{$consumer->name()}] below [Ports/].",
            $port?->file() ?? $consumer->path(),
            $port?->line(),
            $consumer->name(),
            $port === null ? null : $context->displayName($port->owner()),
            $requirement->port(),
            "Move the Port interface below {$consumer->name()}/Ports and keep it consumer-owned.",
        );
    }

    /**
     * @param list<Violation> $violations
     */
    private function validAdapterLocation(
        ?SourceSymbol $adapter,
        DiscoveredModule $consumer,
        ?DiscoveredModule $provider,
        CapabilityRequirement $requirement,
        Severity $severity,
        array &$violations,
    ): ?SourceSymbol {
        $directories = ['Adapters'];

        if ($provider !== null) {
            $directories[] = $provider->name();
        }

        if ($adapter !== null
            && $adapter->owner() === $consumer->moduleClass()
            && $this->isBelow($adapter, $consumer, $directories)) {
            return $adapter;
        }

        $expected = implode('/', $directories).'/';

        $violations[] = new Violation(
            $this->id(),
            'MOD-ADAPTER-002',
            $severity,
            "Capability Adapter [{$requirement->adapter()}] for Module [{$consumer->name()}] must be declared below [{$expected}].",
            $adapter?->file() ?? $consumer->path(),
            $adapter?->line(),
            $consumer->name(),
            $provider?->name(),
            $requirement->adapter(),
            "Move the Adapter below {$consumer->name()}/{$expected} and keep it consumer-owned.",
        );

        return null;
    }

    /**
     * @param array<class-string<Module>, array<string, string>> $declaredAdapters
     * @param list<Violation> $violations
     */
    private function inspectConcreteAdapterReference(
        AnalysisContext $context,
        SourceReference $reference,
        array $declaredAdapters,
        Severity $severity,
        array &$violations,
    ): void {
        $adapterFile = $declaredAdapters[$reference->source()][strtolower($reference->symbol())]
            ?? null;
        $module = $context->module($reference->source());

        if ($adapterFile === null || $module === null) {
            return;
        }

        $sourceFile = $this->normalize($reference->file());

        if ($sourceFile === $this->normalize($module->path()) || $sourceFile === $adapterFile) {
            return;
        }

        $violations[] = new Violation(
            $this->id(),
            'MOD-ADAPTER-005',
            $severity,
            "Module [{$module->name()}] core references concrete Capability Adapter [{$reference->symbol()}].",
            $reference->file(),
            $reference->line(),
            $module->name(),
            null,
            $reference->symbol(),
            'Depend on the consumer-owned Port and let Moduark compose its declared Adapter.',
        );
    }

    /**
     * @param array{
     *     adapters: array<class-string, true>,
     *     providers: array<class-string<Module>, true>
     * } $adapter
     * @param list<Violation> $violations
     */
    private function addWrongProviderViolation(
        AnalysisContext $context,
        SourceReference $reference,
        array $adapter,
        Severity $severity,
        array &$violations,
    ): void {
        $adapters = array_keys($adapter['adapters']);
        sort($adapters, SORT_STRING);
        $providerNames = array_map(
            static fn (string $provider): string => $context->displayName($provider),
            array_keys($adapter['providers']),
        );
        sort($providerNames, SORT_STRING);
        $consumer = $context->displayName($reference->source());
        $target = $context->displayName($reference->target());

        $violations[] = new Violation(
            $this->id(),
            'MOD-ADAPTER-004',
            $severity,
            sprintf(
                'Capability Adapter [%s] for provider Module [%s] references unrelated Module [%s].',
                implode(', ', $adapters),
                implode(', ', $providerNames),
                $target,
            ),
            $reference->file(),
            $reference->line(),
            $consumer,
            $target,
            $reference->symbol(),
            sprintf(
                'Keep this Adapter scoped to Module [%s] or declare a separate Port and Adapter.',
                implode(', ', $providerNames),
            ),
        );
    }

    private function isModuleDependencyReference(
        AnalysisContext $context,
        SourceReference $reference,
    ): bool {
        $source = $context->module($reference->source());
        $target = $context->module($reference->target());
        $descriptor = $context->descriptor($reference->source());

        return $source !== null
            && $target !== null
            && $descriptor !== null
            && $this->normalize($reference->file()) === $this->normalize($source->path())
            && strcasecmp($reference->symbol(), $target->moduleClass()) === 0
            && in_array($target->moduleClass(), $descriptor->dependencies(), true);
    }

    /**
     * @param non-empty-list<string> $directories
     */
    private function isBelow(
        SourceSymbol $symbol,
        DiscoveredModule $module,
        array $directories,
    ): bool {
        $root = rtrim($this->normalize(dirname($module->path())), '/').'/';
        $file = $this->normalize($symbol->file());

        if (! str_starts_with($file, $root)) {
            return false;
        }

        $segments = explode('/', substr($file, strlen($root)));

        foreach ($directories as $index => $directory) {
            if (($segments[$index] ?? null) !== $directory) {
                return false;
            }
        }

        return isset($segments[count($directories)]);
    }

    private function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}

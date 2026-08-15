<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\Rules\AdapterBoundariesRule;
use Cluion\Moduark\Analysis\Source\SourceIndex;
use Cluion\Moduark\Analysis\Source\SourceReference;
use Cluion\Moduark\Analysis\Source\SourceSymbol;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Violation;
use Cluion\Moduark\Capability;
use Cluion\Moduark\CapabilityRequirement;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use PHPUnit\Framework\TestCase;

final class AdapterBoundariesRuleTest extends TestCase
{
    public function test_consumer_owned_port_and_provider_scoped_adapter_pass(): void
    {
        $result = $this->inspect([
            $this->reference(
                AdapterRuleOrderModule::class,
                AdapterRuleUserModule::class,
                AdapterRuleUserModule::class,
                '/modules/Order/OrderModule.php',
                15,
            ),
            $this->reference(
                AdapterRuleOrderModule::class,
                AdapterRuleOrderModule::class,
                AdapterRuleUserLookupPort::class,
                '/modules/Order/Adapters/User/UserLookupAdapter.php',
                8,
            ),
            $this->reference(
                AdapterRuleOrderModule::class,
                AdapterRuleUserModule::class,
                AdapterRuleUserContract::class,
                '/modules/Order/Adapters/User/UserLookupAdapter.php',
                14,
            ),
            $this->reference(
                AdapterRuleOrderModule::class,
                AdapterRuleOrderModule::class,
                AdapterRuleUserLookupPort::class,
                '/modules/Order/Actions/PlaceOrder.php',
                10,
            ),
        ]);

        self::assertTrue($result->passed());
        self::assertSame([], $result->violations());
    }

    public function test_port_must_be_consumer_owned_below_ports(): void
    {
        $result = $this->inspect([], '/modules/Order/Contracts/UserLookup.php');

        self::assertSame([[
            'rule' => 'adapter_boundaries',
            'code' => 'MOD-ADAPTER-001',
            'severity' => 'error',
            'message' => 'Capability Port ['.AdapterRuleUserLookupPort::class
                .'] must be owned by Module [Order] below [Ports/].',
            'file' => '/modules/Order/Contracts/UserLookup.php',
            'line' => 5,
            'consumer' => 'Order',
            'target' => 'Order',
            'symbol' => AdapterRuleUserLookupPort::class,
            'suggestion' => 'Move the Port interface below Order/Ports and keep it consumer-owned.',
        ]], $this->payload($result->violations()));
    }

    public function test_adapter_must_be_consumer_owned_below_provider_directory(): void
    {
        $result = $this->inspect(
            [],
            '/modules/Order/Ports/UserLookup.php',
            '/modules/Order/Adapters/Profile/UserLookupAdapter.php',
        );

        self::assertSame(['MOD-ADAPTER-002'], $this->codes($result->violations()));
        self::assertStringContainsString(
            'must be declared below [Adapters/User/]',
            $result->violations()[0]->message(),
        );
    }

    public function test_port_and_adapter_ownership_are_not_inferred_from_paths(): void
    {
        $result = $this->inspect(
            [],
            portOwner: AdapterRuleUserModule::class,
            adapterOwner: AdapterRuleProductModule::class,
        );

        self::assertSame([
            'MOD-ADAPTER-001',
            'MOD-ADAPTER-002',
        ], $this->codes($result->violations()));
    }

    public function test_consumer_core_cannot_bypass_its_adapter(): void
    {
        $result = $this->inspect([
            $this->reference(
                AdapterRuleOrderModule::class,
                AdapterRuleUserModule::class,
                AdapterRuleUserContract::class,
                '/modules/Order/Actions/PlaceOrder.php',
                12,
            ),
        ]);

        self::assertSame(['MOD-ADAPTER-003'], $this->codes($result->violations()));
        self::assertSame('Order', $result->violations()[0]->consumer());
        self::assertSame('User', $result->violations()[0]->target());
    }

    public function test_adapter_cannot_reference_an_unrelated_provider(): void
    {
        $result = $this->inspect([
            $this->reference(
                AdapterRuleOrderModule::class,
                AdapterRuleProductModule::class,
                AdapterRuleProductContract::class,
                '/modules/Order/Adapters/User/UserLookupAdapter.php',
                17,
            ),
        ]);

        self::assertSame(['MOD-ADAPTER-004'], $this->codes($result->violations()));
        self::assertSame('Product', $result->violations()[0]->target());
    }

    public function test_consumer_core_cannot_reference_its_concrete_adapter(): void
    {
        $result = $this->inspect([
            $this->reference(
                AdapterRuleOrderModule::class,
                AdapterRuleOrderModule::class,
                AdapterRuleUserLookupAdapter::class,
                '/modules/Order/Actions/PlaceOrder.php',
                11,
            ),
        ]);

        self::assertSame(['MOD-ADAPTER-005'], $this->codes($result->violations()));
    }

    public function test_provider_cannot_reference_the_consumer_port(): void
    {
        $result = $this->inspect([
            $this->reference(
                AdapterRuleUserModule::class,
                AdapterRuleOrderModule::class,
                AdapterRuleUserLookupPort::class,
                '/modules/User/Services/UserFinder.php',
                9,
            ),
        ]);

        self::assertSame(['MOD-ADAPTER-003'], $this->codes($result->violations()));
        self::assertSame('User', $result->violations()[0]->consumer());
        self::assertSame('Order', $result->violations()[0]->target());
    }

    public function test_undeclared_module_entry_reference_is_not_metadata(): void
    {
        $result = $this->inspect([
            $this->reference(
                AdapterRuleOrderModule::class,
                AdapterRuleProductModule::class,
                AdapterRuleProductModule::class,
                '/modules/Order/OrderModule.php',
                18,
            ),
        ]);

        self::assertSame(['MOD-ADAPTER-003'], $this->codes($result->violations()));
    }

    public function test_missing_provider_defers_provider_specific_adapter_checks(): void
    {
        $result = $this->inspect(
            [
                $this->reference(
                    AdapterRuleOrderModule::class,
                    AdapterRuleUserModule::class,
                    AdapterRuleUserContract::class,
                    '/modules/Order/Adapters/User/UserLookupAdapter.php',
                    14,
                ),
            ],
            capabilityProviders: [],
        );

        self::assertTrue($result->passed());
    }

    public function test_diagnostics_are_deterministic_for_reordered_references(): void
    {
        $references = [
            $this->reference(
                AdapterRuleOrderModule::class,
                AdapterRuleProductModule::class,
                AdapterRuleProductContract::class,
                '/modules/Order/Adapters/User/UserLookupAdapter.php',
                17,
            ),
            $this->reference(
                AdapterRuleOrderModule::class,
                AdapterRuleUserModule::class,
                AdapterRuleUserContract::class,
                '/modules/Order/Actions/PlaceOrder.php',
                12,
            ),
            $this->reference(
                AdapterRuleOrderModule::class,
                AdapterRuleOrderModule::class,
                AdapterRuleUserLookupAdapter::class,
                '/modules/Order/Actions/PlaceOrder.php',
                11,
            ),
        ];

        $first = $this->inspect($references);
        $second = $this->inspect(array_reverse($references));

        self::assertSame(
            $this->payload($first->violations()),
            $this->payload($second->violations()),
        );
        self::assertSame([
            'MOD-ADAPTER-003',
            'MOD-ADAPTER-004',
            'MOD-ADAPTER-005',
        ], $this->codes($first->violations()));
    }

    /**
     * @param list<SourceReference> $references
     * @param list<class-string<Module>> $capabilityProviders
     * @param class-string<Module> $portOwner
     * @param class-string<Module> $adapterOwner
     */
    private function inspect(
        array $references,
        string $portPath = '/modules/Order/Ports/UserLookup.php',
        string $adapterPath = '/modules/Order/Adapters/User/UserLookupAdapter.php',
        array $capabilityProviders = [AdapterRuleUserModule::class],
        string $portOwner = AdapterRuleOrderModule::class,
        string $adapterOwner = AdapterRuleOrderModule::class,
    ): RuleResult {
        $modules = [
            $this->module('Order', AdapterRuleOrderModule::class),
            $this->module('Product', AdapterRuleProductModule::class),
            $this->module('User', AdapterRuleUserModule::class),
        ];
        $requirement = new CapabilityRequirement(
            AdapterRuleUserLookupCapability::class,
            AdapterRuleUserLookupPort::class,
            AdapterRuleUserLookupAdapter::class,
        );
        $context = new AnalysisContext(
            new ModuleRegistry($modules),
            [
                new ModuleDescriptor(
                    AdapterRuleOrderModule::class,
                    [AdapterRuleUserModule::class],
                    [],
                    [$requirement],
                ),
                new ModuleDescriptor(
                    AdapterRuleProductModule::class,
                    [],
                    [],
                    [],
                    in_array(
                        AdapterRuleProductModule::class,
                        $capabilityProviders,
                        true,
                    ) ? [AdapterRuleUserLookupCapability::class] : [],
                ),
                new ModuleDescriptor(
                    AdapterRuleUserModule::class,
                    [],
                    [],
                    [],
                    in_array(
                        AdapterRuleUserModule::class,
                        $capabilityProviders,
                        true,
                    ) ? [AdapterRuleUserLookupCapability::class] : [],
                ),
            ],
            new SourceIndex([
                new SourceSymbol(
                    AdapterRuleOrderModule::class,
                    AdapterRuleOrderModule::class,
                    '/modules/Order/OrderModule.php',
                    5,
                ),
                new SourceSymbol(
                    AdapterRuleProductModule::class,
                    AdapterRuleProductModule::class,
                    '/modules/Product/ProductModule.php',
                    5,
                ),
                new SourceSymbol(
                    AdapterRuleUserModule::class,
                    AdapterRuleUserModule::class,
                    '/modules/User/UserModule.php',
                    5,
                ),
                new SourceSymbol(
                    AdapterRuleUserLookupPort::class,
                    $portOwner,
                    $portPath,
                    5,
                ),
                new SourceSymbol(
                    AdapterRuleUserLookupAdapter::class,
                    $adapterOwner,
                    $adapterPath,
                    7,
                ),
                new SourceSymbol(
                    AdapterRulePlaceOrder::class,
                    AdapterRuleOrderModule::class,
                    '/modules/Order/Actions/PlaceOrder.php',
                    7,
                ),
                new SourceSymbol(
                    AdapterRuleUserContract::class,
                    AdapterRuleUserModule::class,
                    '/modules/User/Contracts/UserFinder.php',
                    5,
                ),
                new SourceSymbol(
                    AdapterRuleProductContract::class,
                    AdapterRuleProductModule::class,
                    '/modules/Product/Contracts/ProductFinder.php',
                    5,
                ),
            ], $references),
        );

        return (new AdapterBoundariesRule)->inspect(
            $context,
            RuleId::AdapterBoundaries->defaultSeverity(),
        );
    }

    /**
     * @param class-string<Module> $moduleClass
     */
    private function module(string $name, string $moduleClass): DiscoveredModule
    {
        return new DiscoveredModule(
            $name,
            $moduleClass,
            "/modules/{$name}/{$name}Module.php",
            __NAMESPACE__,
        );
    }

    /**
     * @param class-string<Module> $source
     * @param class-string<Module> $target
     */
    private function reference(
        string $source,
        string $target,
        string $symbol,
        string $file,
        int $line,
    ): SourceReference {
        return new SourceReference($source, $target, $symbol, $file, $line);
    }

    /**
     * @param list<Violation> $violations
     * @return list<string>
     */
    private function codes(array $violations): array
    {
        return array_map(
            static fn ($violation): string => $violation->code(),
            $violations,
        );
    }

    /**
     * @param list<Violation> $violations
     * @return list<array<string, int|string|null>>
     */
    private function payload(array $violations): array
    {
        return array_map(
            static fn ($violation): array => $violation->toArray(),
            $violations,
        );
    }
}

interface AdapterRuleUserLookupCapability extends Capability
{
}

interface AdapterRuleUserLookupPort
{
}

final class AdapterRuleUserLookupAdapter implements AdapterRuleUserLookupPort
{
}

final class AdapterRulePlaceOrder
{
}

final class AdapterRuleUserContract
{
}

final class AdapterRuleProductContract
{
}

final class AdapterRuleOrderModule extends Module
{
}

final class AdapterRuleUserModule extends Module
{
}

final class AdapterRuleProductModule extends Module
{
}

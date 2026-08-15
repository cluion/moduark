<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Capability;
use Cluion\Moduark\Capabilities\CapabilityResolver;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Exceptions\CapabilityResolutionFailed;
use Cluion\Moduark\Graph\CapabilityGraph;
use Cluion\Moduark\Graph\CapabilityGraphBuilder;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\LevelTwo\Capabilities\UserLookup as UserLookupCapability;
use Tests\Fixtures\LevelTwo\Modules\Checkout\Adapters\User\UserLookupAdapter as CheckoutUserLookupAdapter;
use Tests\Fixtures\LevelTwo\Modules\Checkout\CheckoutModule;
use Tests\Fixtures\LevelTwo\Modules\Checkout\Ports\UserLookup as CheckoutUserLookup;
use Tests\Fixtures\LevelTwo\Modules\Order\Adapters\User\UserLookupAdapter as OrderUserLookupAdapter;
use Tests\Fixtures\LevelTwo\Modules\Order\OrderModule;
use Tests\Fixtures\LevelTwo\Modules\Order\Ports\UserLookup as OrderUserLookup;
use Tests\Fixtures\LevelTwo\Modules\User\UserModule;

final class CapabilityGraphBuilderTest extends TestCase
{
    public function test_it_builds_a_deterministic_capability_graph_with_evidence(): void
    {
        $first = $this->builder([
            $this->module('Order', OrderModule::class),
            $this->module('Inventory', CapabilityGraphInventoryModule::class),
            $this->module('User', UserModule::class),
            $this->module('Checkout', CheckoutModule::class),
        ])->build();
        $second = $this->builder([
            $this->module('Checkout', CheckoutModule::class),
            $this->module('User', UserModule::class),
            $this->module('Inventory', CapabilityGraphInventoryModule::class),
            $this->module('Order', OrderModule::class),
        ])->build();

        $expected = [
            'modules' => ['Checkout', 'Inventory', 'Order', 'User'],
            'capabilities' => [[
                'name' => 'UserLookup',
                'class' => UserLookupCapability::class,
            ]],
            'edges' => [
                [
                    'type' => 'provides',
                    'module' => UserModule::class,
                    'capability' => UserLookupCapability::class,
                    'evidence' => UserModule::class.'::provides()',
                    'port' => null,
                    'adapter' => null,
                ],
                [
                    'type' => 'requires',
                    'module' => CheckoutModule::class,
                    'capability' => UserLookupCapability::class,
                    'evidence' => CheckoutModule::class.'::requires()',
                    'port' => CheckoutUserLookup::class,
                    'adapter' => CheckoutUserLookupAdapter::class,
                ],
                [
                    'type' => 'requires',
                    'module' => OrderModule::class,
                    'capability' => UserLookupCapability::class,
                    'evidence' => OrderModule::class.'::requires()',
                    'port' => OrderUserLookup::class,
                    'adapter' => OrderUserLookupAdapter::class,
                ],
            ],
        ];

        self::assertSame($expected, $this->payload($first));
        self::assertSame($expected, $this->payload($second));
    }

    public function test_it_keeps_unused_provided_capabilities_visible_without_forcing_selection(): void
    {
        $graph = $this->builder([
            $this->module('BetaProvider', CapabilityGraphAlternativeUnusedProviderModule::class),
            $this->module('AlphaProvider', CapabilityGraphUnusedProviderModule::class),
        ])->build();

        self::assertSame([[
            'name' => 'CapabilityGraphUnused',
            'class' => CapabilityGraphUnused::class,
        ]], array_map(
            static fn ($node): array => $node->toArray(),
            $graph->capabilities(),
        ));
        self::assertSame([
            CapabilityGraphUnusedProviderModule::class,
            CapabilityGraphAlternativeUnusedProviderModule::class,
        ], array_map(
            static fn ($edge): string => $edge->module(),
            $graph->edges(),
        ));
        self::assertSame(['provides', 'provides'], array_map(
            static fn ($edge): string => $edge->type()->value,
            $graph->edges(),
        ));
    }

    public function test_it_rejects_a_missing_provider_before_constructing_the_graph(): void
    {
        $this->expectException(CapabilityResolutionFailed::class);
        $this->expectExceptionMessage(
            'Capability ['.UserLookupCapability::class.'] required by ['
            .OrderModule::class.'] has no provider.',
        );

        $this->builder([
            $this->module('Order', OrderModule::class),
        ])->build();
    }

    public function test_it_rejects_ambiguous_providers_deterministically(): void
    {
        $providers = [
            UserModule::class,
            CapabilityGraphAlternativeUserModule::class,
        ];
        sort($providers, SORT_STRING);

        $this->expectException(CapabilityResolutionFailed::class);
        $this->expectExceptionMessage(
            'Capability ['.UserLookupCapability::class.'] required by ['
            .OrderModule::class.'] has multiple providers ['
            .implode(', ', $providers).'].',
        );

        $this->builder([
            $this->module('AlternativeUser', CapabilityGraphAlternativeUserModule::class),
            $this->module('Order', OrderModule::class),
            $this->module('User', UserModule::class),
        ])->build();
    }

    /**
     * @param list<DiscoveredModule> $modules
     */
    private function builder(array $modules): CapabilityGraphBuilder
    {
        return new CapabilityGraphBuilder(
            new ModuleRegistry($modules),
            new ModuleMetadataCompiler,
            new CapabilityResolver,
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
     * @return array{
     *     modules: list<string>,
     *     capabilities: list<array{name: string, class: class-string<Capability>}>,
     *     edges: list<array{
     *         type: string,
     *         module: class-string<Module>,
     *         capability: class-string<Capability>,
     *         evidence: string,
     *         port: null|class-string,
     *         adapter: null|class-string
     *     }>
     * }
     */
    private function payload(CapabilityGraph $graph): array
    {
        return [
            'modules' => array_map(
                static fn ($node): string => $node->name(),
                $graph->modules(),
            ),
            'capabilities' => array_map(
                static fn ($node): array => $node->toArray(),
                $graph->capabilities(),
            ),
            'edges' => array_map(
                static fn ($edge): array => $edge->toArray(),
                $graph->edges(),
            ),
        ];
    }
}

final class CapabilityGraphInventoryModule extends Module
{
}

interface CapabilityGraphUnused extends Capability
{
}

final class CapabilityGraphUnusedProviderModule extends Module
{
    /** @return list<class-string<Capability>> */
    public function provides(): array
    {
        return [CapabilityGraphUnused::class];
    }
}

final class CapabilityGraphAlternativeUnusedProviderModule extends Module
{
    /** @return list<class-string<Capability>> */
    public function provides(): array
    {
        return [CapabilityGraphUnused::class];
    }
}

final class CapabilityGraphAlternativeUserModule extends Module
{
    /** @return list<class-string<Capability>> */
    public function provides(): array
    {
        return [UserLookupCapability::class];
    }
}

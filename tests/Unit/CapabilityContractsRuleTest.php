<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\Rules\CapabilityContractsRule;
use Cluion\Moduark\Analysis\Source\SourceIndex;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\CapabilityRequirement;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\LevelTwo\Capabilities\UserLookup as UserLookupCapability;
use Tests\Fixtures\LevelTwo\Modules\Checkout\CheckoutModule;
use Tests\Fixtures\LevelTwo\Modules\Order\Adapters\User\UserLookupAdapter as OrderUserLookupAdapter;
use Tests\Fixtures\LevelTwo\Modules\Order\OrderModule;
use Tests\Fixtures\LevelTwo\Modules\Order\Ports\UserLookup as OrderUserLookup;
use Tests\Fixtures\LevelTwo\Modules\User\UserModule;

final class CapabilityContractsRuleTest extends TestCase
{
    public function test_a_complete_capability_graph_passes(): void
    {
        $descriptors = (new ModuleMetadataCompiler)->compileAll([
            OrderModule::class,
            UserModule::class,
            CheckoutModule::class,
        ]);
        $result = $this->rule()->inspect(
            $this->context([
                $this->module('Checkout', CheckoutModule::class),
                $this->module('Order', OrderModule::class),
                $this->module('User', UserModule::class),
            ], array_reverse($descriptors)),
            RuleId::CapabilityContracts->defaultSeverity(),
        );

        self::assertTrue($result->passed());
        self::assertSame([], $result->violations());
    }

    public function test_a_missing_provider_is_reported_with_module_evidence(): void
    {
        $descriptor = (new ModuleMetadataCompiler)->compile(OrderModule::class);
        $result = $this->rule()->inspect(
            $this->context([
                $this->module('Order', OrderModule::class),
            ], [$descriptor]),
            RuleId::CapabilityContracts->defaultSeverity(),
        );

        self::assertSame([[
            'rule' => 'capability_contracts',
            'code' => 'MOD-CAPABILITY-001',
            'severity' => 'error',
            'message' => 'Module [Order] requires Capability ['.UserLookupCapability::class
                .'] with no provider.',
            'file' => '/modules/Order/OrderModule.php',
            'line' => null,
            'consumer' => 'Order',
            'target' => null,
            'symbol' => UserLookupCapability::class,
            'suggestion' => 'Declare Capability ['.UserLookupCapability::class
                .'] in exactly one discovered Module::provides().',
        ]], array_map(
            static fn ($violation): array => $violation->toArray(),
            $result->violations(),
        ));
    }

    public function test_all_missing_requirements_are_reported_in_deterministic_order(): void
    {
        $compiler = new ModuleMetadataCompiler;
        $order = $compiler->compile(OrderModule::class);
        $checkout = $compiler->compile(CheckoutModule::class);
        $first = $this->rule()->inspect(
            $this->context([
                $this->module('Order', OrderModule::class),
                $this->module('Checkout', CheckoutModule::class),
            ], [$order, $checkout]),
            RuleId::CapabilityContracts->defaultSeverity(),
        );
        $second = $this->rule()->inspect(
            $this->context([
                $this->module('Checkout', CheckoutModule::class),
                $this->module('Order', OrderModule::class),
            ], [$checkout, $order]),
            RuleId::CapabilityContracts->defaultSeverity(),
        );
        $firstPayload = array_map(
            static fn ($violation): array => $violation->toArray(),
            $first->violations(),
        );
        $secondPayload = array_map(
            static fn ($violation): array => $violation->toArray(),
            $second->violations(),
        );

        self::assertSame($firstPayload, $secondPayload);
        self::assertSame([
            'Checkout',
            'Order',
        ], array_column($firstPayload, 'consumer'));
        self::assertSame([
            'MOD-CAPABILITY-001',
            'MOD-CAPABILITY-001',
        ], array_column($firstPayload, 'code'));
    }

    public function test_ambiguous_providers_are_sorted_in_the_diagnostic(): void
    {
        $compiler = new ModuleMetadataCompiler;
        $result = $this->rule()->inspect(
            $this->context([
                $this->module('User', UserModule::class),
                $this->module('Order', OrderModule::class),
                $this->module('Alternative', CapabilityRuleAlternativeProviderModule::class),
            ], [
                $compiler->compile(UserModule::class),
                $compiler->compile(OrderModule::class),
                new ModuleDescriptor(
                    CapabilityRuleAlternativeProviderModule::class,
                    [],
                    [],
                    [],
                    [UserLookupCapability::class],
                ),
            ]),
            RuleId::CapabilityContracts->defaultSeverity(),
        );

        self::assertCount(1, $result->violations());
        self::assertSame([
            'rule' => 'capability_contracts',
            'code' => 'MOD-CAPABILITY-002',
            'severity' => 'error',
            'message' => 'Module [Order] requires Capability ['.UserLookupCapability::class
                .'] provided by multiple Modules [Alternative, User].',
            'file' => '/modules/Order/OrderModule.php',
            'line' => null,
            'consumer' => 'Order',
            'target' => 'Alternative, User',
            'symbol' => UserLookupCapability::class,
            'suggestion' => 'Leave exactly one discovered Module providing Capability ['
                .UserLookupCapability::class.'].',
        ], $result->violations()[0]->toArray());
    }

    public function test_a_shared_port_is_reported_for_all_consumer_modules(): void
    {
        $requirement = new CapabilityRequirement(
            UserLookupCapability::class,
            OrderUserLookup::class,
            OrderUserLookupAdapter::class,
        );
        $result = $this->rule()->inspect(
            $this->context([
                $this->module('Beta', CapabilityRuleBetaConsumerModule::class),
                $this->module('User', UserModule::class),
                $this->module('Alpha', CapabilityRuleAlphaConsumerModule::class),
            ], [
                new ModuleDescriptor(
                    CapabilityRuleBetaConsumerModule::class,
                    [],
                    [],
                    [$requirement],
                ),
                (new ModuleMetadataCompiler)->compile(UserModule::class),
                new ModuleDescriptor(
                    CapabilityRuleAlphaConsumerModule::class,
                    [],
                    [],
                    [$requirement],
                ),
            ]),
            RuleId::CapabilityContracts->defaultSeverity(),
        );

        self::assertCount(1, $result->violations());
        self::assertSame([
            'rule' => 'capability_contracts',
            'code' => 'MOD-CAPABILITY-003',
            'severity' => 'error',
            'message' => 'Consumer Modules [Alpha, Beta] require the same Capability Port ['
                .OrderUserLookup::class.'].',
            'file' => '/modules/Alpha/AlphaModule.php',
            'line' => null,
            'consumer' => 'Alpha',
            'target' => 'Beta',
            'symbol' => OrderUserLookup::class,
            'suggestion' => 'Give each consumer Module its own Port interface and CapabilityRequirement mapping.',
        ], $result->violations()[0]->toArray());
    }

    public function test_multiple_unused_providers_remain_valid(): void
    {
        $result = $this->rule()->inspect(
            $this->context([
                $this->module('Alternative', CapabilityRuleAlternativeProviderModule::class),
                $this->module('User', UserModule::class),
            ], [
                new ModuleDescriptor(
                    CapabilityRuleAlternativeProviderModule::class,
                    [],
                    [],
                    [],
                    [UserLookupCapability::class],
                ),
                (new ModuleMetadataCompiler)->compile(UserModule::class),
            ]),
            RuleId::CapabilityContracts->defaultSeverity(),
        );

        self::assertTrue($result->passed());
    }

    private function rule(): CapabilityContractsRule
    {
        return new CapabilityContractsRule;
    }

    /**
     * @param list<DiscoveredModule> $modules
     * @param list<ModuleDescriptor> $descriptors
     */
    private function context(array $modules, array $descriptors): AnalysisContext
    {
        return new AnalysisContext(
            new ModuleRegistry($modules),
            $descriptors,
            new SourceIndex([], []),
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
}

final class CapabilityRuleAlternativeProviderModule extends Module
{
}

final class CapabilityRuleAlphaConsumerModule extends Module
{
}

final class CapabilityRuleBetaConsumerModule extends Module
{
}

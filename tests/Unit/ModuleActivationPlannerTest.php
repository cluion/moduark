<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Capabilities\CapabilityResolver;
use Cluion\Moduark\Discovery\ModuleActivationSet;
use Cluion\Moduark\Discovery\ModuleDiscoverer;
use Cluion\Moduark\Exceptions\ModuleActivationFailed;
use Cluion\Moduark\Lifecycle\Activation\ModuleActivationBlockerCode;
use Cluion\Moduark\Lifecycle\Activation\ModuleActivationIntent;
use Cluion\Moduark\Lifecycle\Activation\ModuleActivationPlanner;
use Cluion\Moduark\Lifecycle\ModuleOrderer;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Registry\ModuleRegistry;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Lifecycle\Activation\Capabilities\Payments;
use Tests\Fixtures\Lifecycle\Activation\Modules\AlternativePayments\AlternativePaymentsModule;
use Tests\Fixtures\Lifecycle\Activation\Modules\CycleAlpha\CycleAlphaModule;
use Tests\Fixtures\Lifecycle\Activation\Modules\CycleBeta\CycleBetaModule;
use Tests\Fixtures\Lifecycle\Activation\Modules\Foundation\FoundationModule;
use Tests\Fixtures\Lifecycle\Activation\Modules\Orders\OrdersModule;
use Tests\Fixtures\Lifecycle\Activation\Modules\Reports\ReportsModule;
use Tests\Fixtures\Lifecycle\Activation\Modules\Stripe\StripeModule;

final class ModuleActivationPlannerTest extends TestCase
{
    private ModuleActivationPlanner $planner;

    private ModuleRegistry $inventory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planner = new ModuleActivationPlanner(
            new ModuleMetadataCompiler,
            new ModuleOrderer,
            new CapabilityResolver,
        );
        $this->inventory = (new ModuleDiscoverer)->discover($this->fixturePath());
    }

    public function test_it_plans_a_valid_leaf_disable_without_mutation(): void
    {
        $current = $this->baseline();
        $plan = $this->planner->plan(
            $this->inventory,
            $current,
            'Reports',
            ModuleActivationIntent::Disable,
        );

        self::assertTrue($plan->executable());
        self::assertFalse($plan->noOp());
        self::assertSame('Reports', $plan->module());
        self::assertSame(ModuleActivationIntent::Disable, $plan->intent());
        self::assertSame([
            'Foundation',
            'Orders',
            'Reports',
            'Stripe',
        ], $plan->before());
        self::assertSame([
            'Foundation',
            'Orders',
            'Stripe',
        ], $plan->after());
        self::assertSame([
            FoundationModule::class,
            OrdersModule::class,
            StripeModule::class,
        ], $plan->orderedModules());
        self::assertSame(
            ModuleActivationSet::only($plan->after())->fingerprint(),
            $plan->activationFingerprint(),
        );
        self::assertSame([], $plan->blockers());
        self::assertTrue($current->includes('Reports'));
    }

    public function test_it_treats_an_existing_state_as_a_validated_no_op(): void
    {
        $current = $this->baseline();
        $plan = $this->planner->plan(
            $this->inventory,
            $current,
            'foundation',
            ModuleActivationIntent::Enable,
        );

        self::assertTrue($plan->executable());
        self::assertTrue($plan->noOp());
        self::assertSame('Foundation', $plan->module());
        self::assertSame($plan->before(), $plan->after());
        self::assertSame($current->fingerprint(), $plan->activationFingerprint());
    }

    public function test_unknown_module_is_an_input_error_instead_of_a_no_op(): void
    {
        $this->expectException(ModuleActivationFailed::class);
        $this->expectExceptionMessage('Unknown Module [Missing].');

        $this->planner->plan(
            $this->inventory,
            $this->baseline(),
            'Missing',
            ModuleActivationIntent::Disable,
        );
    }

    public function test_disabling_a_required_module_reports_every_missing_dependency(): void
    {
        $plan = $this->planner->plan(
            $this->inventory,
            $this->baseline(),
            'Foundation',
            ModuleActivationIntent::Disable,
        );

        self::assertFalse($plan->executable());
        self::assertSame([], $plan->orderedModules());
        self::assertSame([
            [
                'code' => 'missing-dependency',
                'message' => 'Module ['.OrdersModule::class.'] depends on missing module ['
                    .FoundationModule::class.'].',
                'context' => [
                    'module' => OrdersModule::class,
                    'dependency' => FoundationModule::class,
                ],
            ],
        ], $plan->toArray()['blockers']);
    }

    public function test_enabling_a_module_with_a_disabled_dependency_is_blocked(): void
    {
        $current = ModuleActivationSet::fromStates([
            'AlternativePayments' => false,
            'Foundation' => false,
            'Orders' => false,
            'Reports' => false,
            'Stripe' => true,
        ]);
        $plan = $this->planner->plan(
            $this->inventory,
            $current,
            'Orders',
            ModuleActivationIntent::Enable,
        );

        self::assertFalse($plan->executable());
        self::assertSame(
            ModuleActivationBlockerCode::MissingDependency,
            $plan->blockers()[0]->code(),
        );
    }

    public function test_disabling_the_only_capability_provider_is_blocked(): void
    {
        $plan = $this->planner->plan(
            $this->inventory,
            $this->baseline(),
            'Stripe',
            ModuleActivationIntent::Disable,
        );

        self::assertFalse($plan->executable());
        self::assertSame(
            ModuleActivationBlockerCode::MissingCapabilityProvider,
            $plan->blockers()[0]->code(),
        );
        self::assertSame([
            'capability' => Payments::class,
            'consumer' => OrdersModule::class,
        ], $plan->blockers()[0]->context());
    }

    public function test_enabling_a_second_capability_provider_is_blocked(): void
    {
        $plan = $this->planner->plan(
            $this->inventory,
            $this->baseline(),
            'AlternativePayments',
            ModuleActivationIntent::Enable,
        );

        self::assertFalse($plan->executable());
        self::assertSame(
            ModuleActivationBlockerCode::AmbiguousCapabilityProvider,
            $plan->blockers()[0]->code(),
        );
        self::assertSame([
            'capability' => Payments::class,
            'consumer' => OrdersModule::class,
            'providers' => [
                AlternativePaymentsModule::class,
                StripeModule::class,
            ],
        ], $plan->blockers()[0]->context());
    }

    public function test_an_invalid_no_op_cannot_bypass_cycle_validation(): void
    {
        $plan = $this->planner->plan(
            $this->inventory,
            ModuleActivationSet::fromStates([
                'CycleAlpha' => true,
                'CycleBeta' => true,
            ]),
            'CycleAlpha',
            ModuleActivationIntent::Enable,
        );

        self::assertTrue($plan->noOp());
        self::assertFalse($plan->executable());
        self::assertSame(
            ModuleActivationBlockerCode::CircularDependency,
            $plan->blockers()[0]->code(),
        );
        self::assertSame([
            'cycle' => [
                CycleAlphaModule::class,
                CycleBetaModule::class,
                CycleAlphaModule::class,
            ],
        ], $plan->blockers()[0]->context());
    }

    public function test_plan_output_is_deterministic_and_scalar_only(): void
    {
        $reverseInventory = new ModuleRegistry(array_reverse($this->inventory->all()));
        $first = $this->planner->plan(
            $this->inventory,
            $this->baseline(),
            'reports',
            ModuleActivationIntent::Disable,
        )->toArray();
        $second = $this->planner->plan(
            $reverseInventory,
            ModuleActivationSet::fromStates([
                'Stripe' => true,
                'Reports' => true,
                'Orders' => true,
                'Foundation' => true,
                'AlternativePayments' => false,
            ]),
            'Reports',
            ModuleActivationIntent::Disable,
        )->toArray();

        self::assertSame($first, $second);
        self::assertSame($first, unserialize(serialize($first), ['allowed_classes' => false]));

        array_walk_recursive($first, static function (mixed $value): void {
            self::assertTrue(is_scalar($value) || $value === null);
        });
    }

    private function baseline(): ModuleActivationSet
    {
        return ModuleActivationSet::fromStates([
            'AlternativePayments' => false,
            'Foundation' => true,
            'Orders' => true,
            'Reports' => true,
            'Stripe' => true,
        ]);
    }

    private function fixturePath(): string
    {
        return dirname(__DIR__).'/Fixtures/Lifecycle/Activation/Modules';
    }
}

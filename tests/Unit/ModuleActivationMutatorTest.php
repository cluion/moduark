<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Discovery\ModuleActivationSet;
use Cluion\Moduark\Exceptions\ModuleActivationMutationFailed;
use Cluion\Moduark\Lifecycle\Activation\ModuleActivationCacheInvalidator;
use Cluion\Moduark\Lifecycle\Activation\ModuleActivationDriver;
use Cluion\Moduark\Lifecycle\Activation\ModuleActivationIntent;
use Cluion\Moduark\Lifecycle\Activation\ModuleActivationMutator;
use Cluion\Moduark\Lifecycle\Activation\ModuleActivationPlan;
use Cluion\Moduark\Lifecycle\Activation\ModuleActivationState;
use Cluion\Moduark\Lifecycle\Activation\ModuleActivationStore;
use Cluion\Moduark\Registry\ModuleRegistry;
use PHPUnit\Framework\TestCase;

final class ModuleActivationMutatorTest extends TestCase
{
    public function test_no_op_does_not_require_a_store_or_invalidate_caches(): void
    {
        $invalidator = $this->invalidator();
        $mutator = new ModuleActivationMutator($invalidator);
        $plan = new ModuleActivationPlan(
            'User',
            ModuleActivationIntent::Enable,
            true,
            ['User'],
            ['User'],
            [],
            ModuleActivationSet::only(['User'])->fingerprint(),
        );

        self::assertFalse($mutator->apply(
            $plan,
            new ModuleRegistry([]),
            new ModuleActivationState(ModuleActivationDriver::Standalone, ModuleActivationSet::only(['User'])),
        ));
        self::assertSame(0, $invalidator->calls);
    }

    public function test_unsupported_store_is_rejected_before_cache_invalidation(): void
    {
        $invalidator = $this->invalidator();
        $mutator = new ModuleActivationMutator($invalidator);
        $plan = new ModuleActivationPlan(
            'User',
            ModuleActivationIntent::Disable,
            false,
            ['User'],
            [],
            [],
            ModuleActivationSet::only([])->fingerprint(),
        );

        $this->expectException(ModuleActivationMutationFailed::class);
        $this->expectExceptionMessage('does not support atomic mutation');

        try {
            $mutator->apply(
                $plan,
                new ModuleRegistry([]),
                new ModuleActivationState(ModuleActivationDriver::Nwidart, ModuleActivationSet::only(['User'])),
            );
        } finally {
            self::assertSame(0, $invalidator->calls);
        }
    }

    public function test_store_controls_the_invalidation_and_commit_boundary(): void
    {
        $invalidator = $this->invalidator();
        $store = new class implements ModuleActivationStore
        {
            public bool $committed = false;

            public function load(array $knownNames): ModuleActivationSet
            {
                return ModuleActivationSet::only(['User']);
            }

            public function commit(
                ModuleRegistry $inventory,
                ModuleActivationSet $expected,
                ModuleActivationPlan $plan,
                callable $beforeCommit,
            ): void {
                $beforeCommit();
                $this->committed = true;
            }

            public function path(): string
            {
                return '/state.json';
            }
        };
        $plan = new ModuleActivationPlan(
            'User',
            ModuleActivationIntent::Disable,
            false,
            ['User'],
            [],
            [],
            ModuleActivationSet::only([])->fingerprint(),
        );

        self::assertTrue((new ModuleActivationMutator($invalidator))->apply(
            $plan,
            new ModuleRegistry([]),
            new ModuleActivationState(
                ModuleActivationDriver::Standalone,
                ModuleActivationSet::only(['User']),
                $store,
            ),
        ));
        self::assertSame(1, $invalidator->calls);
        self::assertTrue($store->committed);
    }

    private function invalidator(): TestModuleActivationCacheInvalidator
    {
        return new TestModuleActivationCacheInvalidator;
    }
}

final class TestModuleActivationCacheInvalidator implements ModuleActivationCacheInvalidator
{
    public int $calls = 0;

    public function invalidate(): void
    {
        $this->calls++;
    }
}

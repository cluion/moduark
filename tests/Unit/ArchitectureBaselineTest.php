<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Analysis\Baseline\ArchitectureBaseline;
use Cluion\Moduark\Analysis\Baseline\ArchitectureBaselineStore;
use Cluion\Moduark\Analysis\Baseline\BaselineEntry;
use Cluion\Moduark\Analysis\CheckReport;
use Cluion\Moduark\Architecture\EffectiveArchitecture;
use Cluion\Moduark\Architecture\EffectiveRule;
use Cluion\Moduark\Architecture\EffectiveRules;
use Cluion\Moduark\Architecture\Level;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Architecture\Violation;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ArchitectureBaselineTest extends TestCase
{
    public function test_matching_ignores_line_and_message_drift_and_uses_portable_paths(): void
    {
        $baseline = ArchitectureBaseline::fromReport(
            $this->report($this->violation('/workspace/app/Modules/Order/OrderModule.php', 12)),
            '/workspace',
        );
        $current = $this->report(new Violation(
            RuleId::Cycles,
            'MOD-CHECK-001',
            Severity::Error,
            'Updated diagnostic wording.',
            '/workspace/app/Modules/Order/OrderModule.php',
            44,
            'Order',
            'User',
            'Tests\\FixtureSymbol',
        ));

        $filtered = $baseline->apply($current, 'moduark-baseline.json', '/workspace');
        $status = $filtered->baseline();

        self::assertSame([], $filtered->violations());
        self::assertNotNull($status);
        self::assertSame([
            'path' => 'moduark-baseline.json',
            'violations' => 1,
            'matched' => 1,
            'stale' => 0,
            'exceeded' => 0,
        ], $status->toArray());
        self::assertSame(
            'app/Modules/Order/OrderModule.php',
            $baseline->toArray()['violations'][0]['file'],
        );
    }

    public function test_increased_identity_count_reports_the_whole_group(): void
    {
        $baseline = ArchitectureBaseline::fromReport(
            $this->report($this->violation('/workspace/app/Modules/Order/OrderModule.php', 12)),
            '/workspace',
        );
        $current = $this->report(
            $this->violation('/workspace/app/Modules/Order/OrderModule.php', 12),
            $this->violation('/workspace/app/Modules/Order/OrderModule.php', 20),
        );

        $filtered = $baseline->apply($current, 'moduark-baseline.json', '/workspace');
        $status = $filtered->baseline();

        self::assertCount(2, $filtered->violations());
        self::assertNotNull($status);
        self::assertSame(0, $status->matched());
        self::assertSame(2, $status->exceeded());
    }

    public function test_undeclared_dependency_baselines_use_stable_module_pair_identity(): void
    {
        $entry = BaselineEntry::fromViolation(new Violation(
            RuleId::UndeclaredDependencies,
            'MOD-DEPENDENCY-002',
            Severity::Error,
            'Module [Order] uses [User] without declaring the dependency.',
            '/workspace/app/Modules/Order/Actions/PlaceOrder.php',
            24,
            'Order',
            'User',
            'App\\Modules\\User\\Internal\\UserRepository',
        ), '/workspace');

        self::assertSame([
            'rule' => 'undeclared_dependencies',
            'code' => 'MOD-DEPENDENCY-002',
            'severity' => 'error',
            'file' => null,
            'consumer' => 'Order',
            'target' => 'User',
            'symbol' => null,
            'count' => 1,
        ], $entry->toArray());
    }

    public function test_beta_symbol_identity_becomes_stale_without_hiding_the_current_pair(): void
    {
        $baseline = ArchitectureBaseline::fromArray([
            'schema_version' => 1,
            'generated_level' => 1,
            'violations' => [[
                'rule' => 'undeclared_dependencies',
                'code' => 'MOD-DEPENDENCY-002',
                'severity' => 'error',
                'file' => 'app/Modules/Order/Actions/PlaceOrder.php',
                'consumer' => 'Order',
                'target' => 'User',
                'symbol' => 'App\\Modules\\User\\Internal\\UserRepository',
                'count' => 4,
            ]],
        ]);
        $current = new Violation(
            RuleId::UndeclaredDependencies,
            'MOD-DEPENDENCY-002',
            Severity::Error,
            'Module [Order] uses [User] without declaring the dependency.',
            '/workspace/app/Modules/Order/Actions/PlaceOrder.php',
            24,
            'Order',
            'User',
            'App\\Modules\\User\\Internal\\UserRepository',
        );
        $report = new CheckReport(
            new EffectiveArchitecture(
                Level::Modular,
                Level::Modular,
                new EffectiveRules([
                    new EffectiveRule(RuleId::UndeclaredDependencies, true, Severity::Error),
                ]),
            ),
            [new RuleResult(RuleId::UndeclaredDependencies, [$current])],
            [],
        );

        $filtered = $baseline->apply($report, '/workspace/moduark-baseline.json', '/workspace');
        $status = $filtered->baseline();

        self::assertSame([$current], $filtered->violations());
        self::assertNotNull($status);
        self::assertSame(0, $status->matched());
        self::assertSame(4, $status->stale());
        self::assertSame(0, $status->exceeded());
        self::assertSame([], $baseline->prune($report, '/workspace')->toArray()['violations']);
    }

    public function test_prune_only_removes_stale_debt_and_never_adds_new_violations(): void
    {
        $oldOrder = $this->violation('/workspace/app/Modules/Order/OrderModule.php', 12);
        $oldUser = new Violation(
            RuleId::Cycles,
            'MOD-CHECK-001',
            Severity::Error,
            'Fixture architecture violation.',
            '/workspace/app/Modules/User/UserModule.php',
            10,
            'User',
            'Order',
            'Tests\\OtherFixtureSymbol',
        );
        $baseline = ArchitectureBaseline::fromReport(
            $this->report($oldOrder, $this->violation('/workspace/app/Modules/Order/OrderModule.php', 20), $oldUser),
            '/workspace',
        );
        $newViolation = new Violation(
            RuleId::Cycles,
            'MOD-CHECK-001',
            Severity::Error,
            'New debt must not be adopted by prune.',
            '/workspace/app/Modules/Billing/BillingModule.php',
            5,
            'Billing',
            'Order',
            'Tests\\NewFixtureSymbol',
        );

        $pruned = $baseline->prune($this->report($oldOrder, $newViolation), '/workspace');
        $entries = $pruned->toArray()['violations'];

        self::assertCount(1, $entries);
        self::assertSame('Order', $entries[0]['consumer']);
        self::assertSame(1, $entries[0]['count']);
    }

    public function test_store_rejects_an_unknown_schema_version(): void
    {
        $path = sys_get_temp_dir().'/moduark-invalid-baseline-'.bin2hex(random_bytes(6)).'.json';
        file_put_contents($path, '{"schema_version":2,"generated_level":1,"violations":[]}');

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('schema_version must be 1');

            (new ArchitectureBaselineStore)->load($path);
        } finally {
            unlink($path);
        }
    }

    private function violation(string $file, int $line): Violation
    {
        return new Violation(
            RuleId::Cycles,
            'MOD-CHECK-001',
            Severity::Error,
            'Fixture architecture violation.',
            $file,
            $line,
            'Order',
            'User',
            'Tests\\FixtureSymbol',
        );
    }

    private function report(Violation ...$violations): CheckReport
    {
        return new CheckReport(
            new EffectiveArchitecture(
                Level::Modular,
                Level::Modular,
                new EffectiveRules([
                    new EffectiveRule(RuleId::Cycles, true, Severity::Error),
                ]),
            ),
            [new RuleResult(RuleId::Cycles, array_values($violations))],
            [],
        );
    }
}

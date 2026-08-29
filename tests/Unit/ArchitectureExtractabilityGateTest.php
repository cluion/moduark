<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Analysis\CheckReport;
use Cluion\Moduark\Analysis\RawArchitectureCheck;
use Cluion\Moduark\Architecture\EffectiveArchitecture;
use Cluion\Moduark\Architecture\EffectiveRule;
use Cluion\Moduark\Architecture\EffectiveRules;
use Cluion\Moduark\Architecture\Level;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Architecture\Violation;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Extraction\ArchitectureExtractabilityGate;
use Cluion\Moduark\Extraction\ExtractabilityCheck;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tests\Fixtures\Analysis\Modules\Order\OrderModule;

final class ArchitectureExtractabilityGateTest extends TestCase
{
    /** @var list<RuleId> */
    private const RULES = [
        RuleId::UndeclaredDependencies,
        RuleId::CapabilityContracts,
        RuleId::DatabaseOwnership,
        RuleId::CrossModuleForeignKeys,
        RuleId::CrossModuleTransactions,
        RuleId::ExplicitPublicExports,
    ];

    public function test_clean_raw_level_three_evidence_passes_in_stable_order(): void
    {
        $architecture = new RecordingRawArchitectureCheck($this->report());
        $checks = (new ArchitectureExtractabilityGate($architecture))->checks($this->module());

        self::assertSame(Level::Isolated, $architecture->level);
        self::assertSame([
            'MOD-EXTRACT-DEPENDENCY-001',
            'MOD-EXTRACT-CAPABILITY-001',
            'MOD-EXTRACT-TABLE-001',
            'MOD-EXTRACT-FK-001',
            'MOD-EXTRACT-TRANSACTION-001',
            'MOD-EXTRACT-EXPORT-001',
        ], array_map(static fn (ExtractabilityCheck $check): string => $check->code(), $checks));
        self::assertSame(
            array_fill(0, 6, ExtractabilityCheck::PASSED),
            array_map(static fn (ExtractabilityCheck $check): string => $check->status(), $checks),
        );
    }

    public function test_consumer_target_lists_and_warnings_block_but_unrelated_evidence_does_not(): void
    {
        $report = $this->report([
            RuleId::UndeclaredDependencies->value => [$this->violation(
                RuleId::UndeclaredDependencies,
                'MOD-DEPENDENCY-002',
                Severity::Error,
                'Order',
                'User',
            )],
            RuleId::CapabilityContracts->value => [$this->violation(
                RuleId::CapabilityContracts,
                'MOD-CAPABILITY-002',
                Severity::Error,
                'Billing',
                'Order, User',
            )],
            RuleId::DatabaseOwnership->value => [$this->violation(
                RuleId::DatabaseOwnership,
                'MOD-TABLE-001',
                Severity::Error,
                'Billing',
                'User',
            )],
            RuleId::CrossModuleForeignKeys->value => [$this->violation(
                RuleId::CrossModuleForeignKeys,
                'MOD-FK-001',
                Severity::Warning,
                'Order',
                'User',
            )],
            RuleId::CrossModuleTransactions->value => [$this->violation(
                RuleId::CrossModuleTransactions,
                'MOD-TRANSACTION-001',
                Severity::Warning,
                'Checkout',
                'Billing, Order',
            )],
            RuleId::ExplicitPublicExports->value => [$this->violation(
                RuleId::ExplicitPublicExports,
                'MOD-EXPORT-001',
                Severity::Error,
                'Billing',
                'Order',
            )],
        ]);

        $checks = (new ArchitectureExtractabilityGate(
            new RecordingRawArchitectureCheck($report),
        ))->checks($this->module());
        $blocked = array_values(array_filter(
            $checks,
            static fn (ExtractabilityCheck $check): bool => $check->blocked(),
        ));

        self::assertSame([
            'MOD-EXTRACT-DEPENDENCY-001',
            'MOD-EXTRACT-CAPABILITY-001',
            'MOD-EXTRACT-FK-001',
            'MOD-EXTRACT-TRANSACTION-001',
            'MOD-EXTRACT-EXPORT-001',
        ], array_map(static fn (ExtractabilityCheck $check): string => $check->code(), $blocked));
        self::assertSame(ExtractabilityCheck::PASSED, $checks[2]->status());
        self::assertStringStartsWith('MOD-FK-001={', $checks[3]->evidence()[0]);
        self::assertStringContainsString('"severity":"warning"', $checks[3]->evidence()[0]);
    }

    public function test_required_rule_that_was_not_evaluated_is_a_blocker(): void
    {
        $report = $this->report(unevaluated: [RuleId::DatabaseOwnership]);
        $checks = (new ArchitectureExtractabilityGate(
            new RecordingRawArchitectureCheck($report),
        ))->checks($this->module());

        self::assertSame(ExtractabilityCheck::BLOCKED, $checks[2]->status());
        self::assertSame(
            ['rule_not_evaluated=database_ownership'],
            $checks[2]->evidence(),
        );
    }

    public function test_required_rule_that_was_disabled_is_a_blocker(): void
    {
        $report = $this->report(
            unevaluated: [RuleId::ExplicitPublicExports],
            disabled: [RuleId::ExplicitPublicExports],
        );
        $checks = (new ArchitectureExtractabilityGate(
            new RecordingRawArchitectureCheck($report),
        ))->checks($this->module());

        self::assertSame(ExtractabilityCheck::BLOCKED, $checks[5]->status());
        self::assertSame(
            ['rule_not_evaluated=explicit_public_exports'],
            $checks[5]->evidence(),
        );
    }

    /**
     * @param array<string, list<Violation>> $violations
     * @param list<RuleId> $unevaluated
     * @param list<RuleId> $disabled
     */
    private function report(
        array $violations = [],
        array $unevaluated = [],
        array $disabled = [],
    ): CheckReport {
        $rules = [];
        $results = [];

        foreach (self::RULES as $rule) {
            $rules[] = new EffectiveRule(
                $rule,
                ! in_array($rule, $disabled, true),
                $rule->defaultSeverity(),
            );

            if (! in_array($rule, $unevaluated, true)) {
                $results[] = new RuleResult($rule, $violations[$rule->value] ?? []);
            }
        }

        return new CheckReport(
            new EffectiveArchitecture(
                Level::Modular,
                Level::Isolated,
                new EffectiveRules($rules),
            ),
            $results,
            $unevaluated,
        );
    }

    private function violation(
        RuleId $rule,
        string $code,
        Severity $severity,
        string $consumer,
        string $target,
    ): Violation {
        return new Violation(
            $rule,
            $code,
            $severity,
            'Architecture evidence was found.',
            '/workspace/Modules/'.$consumer.'/Evidence.php',
            10,
            $consumer,
            $target,
            'Evidence',
        );
    }

    private function module(): DiscoveredModule
    {
        $file = (new ReflectionClass(OrderModule::class))->getFileName();
        self::assertIsString($file);

        return new DiscoveredModule(
            'Order',
            OrderModule::class,
            $file,
            'Tests\\Fixtures\\Analysis\\Modules\\Order',
        );
    }
}

final class RecordingRawArchitectureCheck implements RawArchitectureCheck
{
    public ?Level $level = null;

    public function __construct(private readonly CheckReport $report)
    {
    }

    public function check(?Level $level = null): CheckReport
    {
        $this->level = $level;

        return $this->report;
    }
}

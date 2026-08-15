<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Analysis\CheckReport;
use Cluion\Moduark\Analysis\Export\GithubCheckReportExporter;
use Cluion\Moduark\Architecture\EffectiveArchitecture;
use Cluion\Moduark\Architecture\EffectiveRule;
use Cluion\Moduark\Architecture\EffectiveRules;
use Cluion\Moduark\Architecture\Level;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Architecture\Violation;
use PHPUnit\Framework\TestCase;

final class GithubCheckReportExporterTest extends TestCase
{
    public function test_passing_report_exports_one_notice(): void
    {
        $report = new CheckReport(
            $this->architecture(Level::Modular, [RuleId::Cycles]),
            [new RuleResult(RuleId::Cycles)],
            [],
        );

        self::assertSame(
            '::notice title=Moduark architecture check::'
                .'Architecture check passed: 1 rule evaluated at Level 1 (Modular).',
            (new GithubCheckReportExporter)->export($report, '/workspace'),
        );
    }

    public function test_violations_export_error_and_warning_annotations_with_escaping(): void
    {
        $error = new Violation(
            RuleId::Cycles,
            'MOD-CHECK-001',
            Severity::Error,
            "Fixture 100% failed.\nNext line.",
            '/workspace/app/Modules/Order,Legacy/OrderModule.php',
            12,
            'Order',
            'User',
            'Tests\\FixtureSymbol',
            'Break dependency: now.',
        );
        $warning = new Violation(
            RuleId::CrossModuleTransactions,
            'MOD-CHECK-002',
            Severity::Warning,
            'A cross-Module transaction was found.',
        );
        $report = new CheckReport(
            $this->architecture(
                Level::Modular,
                [RuleId::Cycles, RuleId::CrossModuleTransactions],
            ),
            [
                new RuleResult(RuleId::Cycles, [$error]),
                new RuleResult(RuleId::CrossModuleTransactions, [$warning]),
            ],
            [],
        );

        self::assertSame(implode(PHP_EOL, [
            '::error file=app/Modules/Order%2CLegacy/OrderModule.php,line=12,'
                .'title=MOD-CHECK-001 cycles::Fixture 100%25 failed.%0ANext line.'
                .'%0AModules: Order -> User%0AEvidence: Tests\\FixtureSymbol'
                .'%0ASuggestion: Break dependency: now.',
            '::warning title=MOD-CHECK-002 cross_module_transactions::'
                .'A cross-Module transaction was found.',
        ]), (new GithubCheckReportExporter)->export($report, '/workspace'));
    }

    public function test_incomplete_report_exports_unavailable_rules_as_an_error(): void
    {
        $report = new CheckReport(
            $this->architecture(Level::Isolated, []),
            [],
            [RuleId::DatabaseOwnership, RuleId::MigrationOwnership],
        );

        self::assertSame(
            '::error title=Moduark architecture check::'
                .'Architecture analysis is incomplete at Level 3 (Isolated).%0A'
                .'Unavailable rule implementations: database_ownership, migration_ownership',
            (new GithubCheckReportExporter)->export($report, '/workspace'),
        );
    }

    public function test_tool_error_uses_source_location_and_escapes_properties(): void
    {
        self::assertSame(
            '::error file=app/Modules/Order%2CLegacy/OrderModule.php,line=17,'
                .'title=MOD-ANALYSIS-001::Unable to parse Module source.'
                .'%0ALocation: /workspace/app/Modules/Order,Legacy/OrderModule.php:17'
                .'%0ASuggestion: Fix 100%25 now.',
            (new GithubCheckReportExporter)->exportToolError(
                'MOD-ANALYSIS-001',
                'Unable to parse Module source.',
                '/workspace/app/Modules/Order,Legacy/OrderModule.php:17',
                'Fix 100% now.',
                '/workspace',
            ),
        );
    }

    public function test_windows_source_location_keeps_drive_colon_out_of_the_line_number(): void
    {
        self::assertSame(
            '::error file=app/Modules/User/UserModule.php,line=9,title=MOD-ANALYSIS-001::'
                .'Unable to parse Module source.'
                .'%0ALocation: C:\\Repo\\app\\Modules\\User\\UserModule.php:9',
            (new GithubCheckReportExporter)->exportToolError(
                'MOD-ANALYSIS-001',
                'Unable to parse Module source.',
                'C:\\Repo\\app\\Modules\\User\\UserModule.php:9',
                null,
                'c:\\repo',
            ),
        );
    }

    /**
     * @param list<RuleId> $rules
     */
    private function architecture(Level $level, array $rules): EffectiveArchitecture
    {
        return new EffectiveArchitecture(
            $level,
            $level,
            new EffectiveRules(array_map(
                static fn (RuleId $rule): EffectiveRule => new EffectiveRule(
                    $rule,
                    true,
                    $rule->defaultSeverity(),
                ),
                $rules,
            )),
        );
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Analysis\ArchitectureCheck;
use Cluion\Moduark\Analysis\CheckReport;
use Cluion\Moduark\Architecture\EffectiveArchitecture;
use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Architecture\Level;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RulePresets;
use Cluion\Moduark\Architecture\RuleResolver;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Architecture\Violation;
use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Exceptions\SourceAnalysisFailed;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class ModuleCheckCommandTest extends TestCase
{
    public function test_level_zero_check_passes(): void
    {
        $this->command('module:check --level=0')
            ->expectsOutputToContain(
                'Architecture check passed: 2 rules evaluated at Level 0 (Organization).',
            )
            ->assertSuccessful();
    }

    public function test_default_level_check_passes(): void
    {
        $this->command('module:check')
            ->expectsOutputToContain(
                'Architecture check passed: 6 rules evaluated at Level 1 (Modular).',
            )
            ->assertSuccessful();
    }

    public function test_level_two_check_passes(): void
    {
        $this->command('module:check --level=2')
            ->expectsOutputToContain(
                'Architecture check passed: 8 rules evaluated at Level 2 (Decoupled).',
            )
            ->assertSuccessful();
    }

    #[DataProvider('invalidLevels')]
    public function test_invalid_level_is_a_tool_error(string $level): void
    {
        $this->command("module:check --level={$level}")
            ->expectsOutputToContain('The --level option must be an integer from 0 to 3.')
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLevels(): iterable
    {
        yield 'negative' => ['-1'];
        yield 'too high' => ['4'];
        yield 'not numeric' => ['strict'];
        yield 'decimal' => ['1.0'];
    }

    public function test_level_zero_check_passes_after_config_cache(): void
    {
        try {
            $this->command('config:cache')->assertSuccessful();
            $this->refreshApplication();

            $this->command('module:check --level=0')
                ->expectsOutputToContain(
                    'Architecture check passed: 2 rules evaluated at Level 0 (Organization).',
                )
                ->assertSuccessful();
        } finally {
            $this->command('config:clear')->run();
        }
    }

    public function test_default_level_source_analysis_survives_config_cache(): void
    {
        try {
            $this->command('config:cache')->assertSuccessful();
            $this->refreshApplication();

            $this->command('module:check')
                ->expectsOutputToContain(
                    'Architecture check passed: 6 rules evaluated at Level 1 (Modular).',
                )
                ->assertSuccessful();
        } finally {
            $this->command('config:clear')->run();
        }
    }

    #[DataProvider('diagnosticCases')]
    public function test_diagnostics_and_exit_policy_are_rendered(
        Severity $severity,
        int $exitCode,
        string $summary,
    ): void {
        $rule = $severity === Severity::Error
            ? RuleId::Cycles
            : RuleId::CrossModuleTransactions;
        $report = $this->diagnosticReport($rule, $severity);

        $this->application()->instance(
            ArchitectureCheck::class,
            new class($report) implements ArchitectureCheck
            {
                public function __construct(private readonly CheckReport $report)
                {
                }

                public function check(?Level $level = null): CheckReport
                {
                    return $this->report;
                }
            },
        );

        $this->command('module:check')
            ->expectsOutputToContain('MOD-CHECK-001')
            ->expectsOutputToContain('Tests\\FixtureSymbol')
            ->expectsOutputToContain('Break the dependency.')
            ->expectsOutputToContain($summary)
            ->assertExitCode($exitCode);
    }

    /**
     * @return iterable<string, array{Severity, int, string}>
     */
    public static function diagnosticCases(): iterable
    {
        yield 'blocking error' => [
            Severity::Error,
            ExitPolicy::VIOLATIONS_FOUND,
            'Architecture check failed with 1 blocking violation.',
        ];

        yield 'non-blocking warning' => [
            Severity::Warning,
            ExitPolicy::SUCCESS,
            'Architecture check passed with 1 warning.',
        ];
    }

    public function test_analysis_failure_is_a_tool_error(): void
    {
        $this->application()->instance(
            ArchitectureCheck::class,
            new class implements ArchitectureCheck
            {
                public function check(?Level $level = null): CheckReport
                {
                    throw new RuntimeException('Fixture analyzer failed.');
                }
            },
        );

        $this->command('module:check --level=0')
            ->expectsOutputToContain(
                'Architecture analysis could not be completed: Fixture analyzer failed.',
            )
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    public function test_source_analysis_failure_is_an_actionable_tool_error(): void
    {
        $this->application()->instance(
            ArchitectureCheck::class,
            new class implements ArchitectureCheck
            {
                public function check(?Level $level = null): CheckReport
                {
                    throw SourceAnalysisFailed::invalidSyntax(
                        '/app/Modules/Order/Actions/CreateOrder.php',
                        17,
                        'Unexpected token "}"',
                    );
                }
            },
        );

        $this->command('module:check')
            ->expectsOutputToContain('Architecture source analysis could not be completed.')
            ->expectsOutputToContain('MOD-ANALYSIS-001 Unable to parse Module source')
            ->expectsOutputToContain('Location: /app/Modules/Order/Actions/CreateOrder.php:17')
            ->expectsOutputToContain(
                'Suggestion: Fix the PHP syntax at the reported location, then rerun module:check.',
            )
            ->expectsOutputToContain(
                'Result: incomplete; no architecture pass result was produced.',
            )
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    private function diagnosticReport(RuleId $rule, Severity $severity): CheckReport
    {
        $architecture = $this->architecture($rule);
        $violation = new Violation(
            $rule,
            'MOD-CHECK-001',
            $severity,
            'Fixture architecture violation.',
            '/app/Modules/Order/OrderModule.php',
            12,
            'Order',
            'User',
            'Tests\\FixtureSymbol',
            'Break the dependency.',
        );

        return new CheckReport(
            $architecture,
            [new RuleResult($rule, [$violation])],
            [],
        );
    }

    private function architecture(RuleId $enabledRule): EffectiveArchitecture
    {
        $overrides = [];

        foreach (RuleId::cases() as $rule) {
            $overrides[$rule->value] = $rule === $enabledRule;
        }

        $configuration = ModulesConfig::from(
            [
                'path' => '/app/Modules',
                'architecture' => [
                    'level' => 0,
                    'rules' => [],
                ],
            ],
            [
                'architecture' => [
                    'rules' => $overrides,
                ],
            ],
        );

        return (new RuleResolver(new RulePresets))->resolve($configuration);
    }
}

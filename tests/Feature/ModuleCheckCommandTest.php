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
use Illuminate\Contracts\Console\Kernel;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
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

    public function test_json_check_output_is_machine_readable_and_deterministic(): void
    {
        [$firstExitCode, $first, $firstJson] = $this->jsonCheck();
        [$secondExitCode, $second, $secondJson] = $this->jsonCheck();

        self::assertSame(ExitPolicy::SUCCESS, $firstExitCode);
        self::assertSame($firstExitCode, $secondExitCode);
        self::assertSame($firstJson, $secondJson);
        self::assertSame($first, $second);
        self::assertSame(1, $first['schema_version']);
        self::assertSame('passed', $first['status']);
        self::assertTrue($first['complete']);
        self::assertSame(ExitPolicy::SUCCESS, $first['exit_code']);
        self::assertSame([
            'rules_evaluated' => 6,
            'violations' => 0,
            'errors' => 0,
            'warnings' => 0,
        ], $first['summary']);
        self::assertSame([], $first['unavailable_rules']);
        self::assertNull($first['baseline']);
        $results = $first['results'];
        self::assertIsArray($results);
        self::assertCount(6, $results);
        self::assertNull($first['error']);
    }

    public function test_json_check_output_preserves_blocking_violation_context(): void
    {
        $report = $this->diagnosticReport(RuleId::Cycles, Severity::Error);

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

        [$exitCode, $payload] = $this->jsonCheck();

        self::assertSame(ExitPolicy::VIOLATIONS_FOUND, $exitCode);
        self::assertSame('violations_found', $payload['status']);
        self::assertTrue($payload['complete']);
        self::assertSame(ExitPolicy::VIOLATIONS_FOUND, $payload['exit_code']);
        self::assertSame([
            'rules_evaluated' => 1,
            'violations' => 1,
            'errors' => 1,
            'warnings' => 0,
        ], $payload['summary']);
        self::assertSame([
            [
                'rule' => 'cycles',
                'passed' => false,
                'violations' => [[
                    'rule' => 'cycles',
                    'code' => 'MOD-CHECK-001',
                    'severity' => 'error',
                    'message' => 'Fixture architecture violation.',
                    'file' => '/app/Modules/Order/OrderModule.php',
                    'line' => 12,
                    'consumer' => 'Order',
                    'target' => 'User',
                    'symbol' => 'Tests\\FixtureSymbol',
                    'suggestion' => 'Break the dependency.',
                ]],
            ],
        ], $payload['results']);
    }

    public function test_json_check_output_reports_level_three_as_incomplete(): void
    {
        [$exitCode, $payload] = $this->jsonCheck(['--level' => '3']);

        self::assertSame(ExitPolicy::TOOL_ERROR, $exitCode);
        self::assertSame('incomplete', $payload['status']);
        self::assertFalse($payload['complete']);
        self::assertSame(ExitPolicy::TOOL_ERROR, $payload['exit_code']);
        $architecture = $payload['architecture'];
        self::assertIsArray($architecture);
        self::assertSame(3, $architecture['level']);
        self::assertSame('Isolated', $architecture['level_label']);
        self::assertSame([
            'cross_module_model_access',
            'database_ownership',
            'migration_ownership',
            'cross_module_foreign_keys',
            'cross_module_transactions',
            'explicit_public_exports',
        ], $payload['unavailable_rules']);
    }

    public function test_invalid_check_output_format_is_a_tool_error(): void
    {
        $this->command('module:check --format=xml')
            ->expectsOutputToContain('The --format option must be text, json, or github.')
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    public function test_github_check_output_emits_a_notice_for_a_pass(): void
    {
        $this->command('module:check --format=github')
            ->expectsOutput(
                '::notice title=Moduark architecture check::'
                    .'Architecture check passed: 6 rules evaluated at Level 1 (Modular).',
            )
            ->assertSuccessful();
    }

    public function test_github_check_output_preserves_tool_error_exit_code(): void
    {
        $this->command('module:check --level=4 --format=github')
            ->expectsOutput(
                '::error title=MOD-CHECK-OPTION-001::'
                    .'The --level option must be an integer from 0 to 3.%0A'
                    .'Suggestion: Pass one of --level=0, --level=1, --level=2, or --level=3.',
            )
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    public function test_invalid_level_uses_the_json_tool_error_contract(): void
    {
        [$exitCode, $payload] = $this->jsonCheck(['--level' => '4']);

        self::assertSame(ExitPolicy::TOOL_ERROR, $exitCode);
        self::assertSame('incomplete', $payload['status']);
        self::assertFalse($payload['complete']);
        self::assertSame(ExitPolicy::TOOL_ERROR, $payload['exit_code']);
        self::assertNull($payload['architecture']);
        self::assertSame([
            'rules_evaluated' => 0,
            'violations' => 0,
            'errors' => 0,
            'warnings' => 0,
        ], $payload['summary']);
        self::assertSame([], $payload['unavailable_rules']);
        self::assertNull($payload['baseline']);
        self::assertSame([], $payload['results']);
        self::assertSame([
            'code' => 'MOD-CHECK-OPTION-001',
            'message' => 'The --level option must be an integer from 0 to 3.',
            'location' => null,
            'suggestion' => 'Pass one of --level=0, --level=1, --level=2, or --level=3.',
        ], $payload['error']);
    }

    public function test_source_analysis_failure_uses_the_json_tool_error_contract(): void
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

        [$exitCode, $payload] = $this->jsonCheck();

        self::assertSame(ExitPolicy::TOOL_ERROR, $exitCode);
        self::assertSame('incomplete', $payload['status']);
        self::assertFalse($payload['complete']);
        self::assertSame(ExitPolicy::TOOL_ERROR, $payload['exit_code']);
        self::assertSame([
            'code' => 'MOD-ANALYSIS-001',
            'message' => 'Unable to parse Module source '
                .'[/app/Modules/Order/Actions/CreateOrder.php:17]: Unexpected token "}"',
            'location' => '/app/Modules/Order/Actions/CreateOrder.php:17',
            'suggestion' => 'Fix the PHP syntax at the reported location, then rerun module:check.',
        ], $payload['error']);
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

    /**
     * @param array<string, string> $parameters
     * @return array{int, array<mixed>, string}
     */
    private function jsonCheck(array $parameters = []): array
    {
        $output = new BufferedOutput;
        $exitCode = $this->application()->make(Kernel::class)->call(
            'module:check',
            ['--format' => 'json', ...$parameters],
            $output,
        );
        $json = trim($output->fetch());
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);

        return [$exitCode, $payload, $json];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Analysis\ArchitectureCheck;
use Cluion\Moduark\Analysis\Baseline\ArchitectureBaseline;
use Cluion\Moduark\Analysis\Baseline\ArchitectureBaselineStore;
use Cluion\Moduark\Analysis\Baseline\BaselineArchitectureCheck;
use Cluion\Moduark\Analysis\CheckReport;
use Cluion\Moduark\Analysis\RawArchitectureCheck;
use Cluion\Moduark\Analysis\Suppression\SuppressionArchitectureCheck;
use Cluion\Moduark\Analysis\Suppression\SuppressionManifestStore;
use Cluion\Moduark\Analysis\UnbaselinedArchitectureCheck;
use Cluion\Moduark\Architecture\EffectiveArchitecture;
use Cluion\Moduark\Architecture\EffectiveRule;
use Cluion\Moduark\Architecture\EffectiveRules;
use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Architecture\Level;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Architecture\Violation;
use Cluion\Moduark\Configuration\ModulesConfig;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

final class ModuleSuppressionCommandTest extends TestCase
{
    private string $temporaryPath;

    private string $suppressionPath;

    private string $baselinePath;

    private MutableSuppressionRawCheck $raw;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryPath = sys_get_temp_dir().'/moduark-suppressions-'.bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->temporaryPath, 0755, true));
        $this->suppressionPath = $this->temporaryPath.'/moduark-suppressions.json';
        $this->baselinePath = $this->temporaryPath.'/moduark-baseline.json';
        $configuration = ModulesConfig::from(
            [
                'path' => $this->application()->basePath('app/Modules'),
                'architecture' => [
                    'level' => 1,
                    'baseline' => $this->baselinePath,
                    'suppressions' => $this->suppressionPath,
                    'rules' => [],
                ],
            ],
            [],
        );
        $this->raw = new MutableSuppressionRawCheck($this->report($this->violation()));
        $suppression = new SuppressionArchitectureCheck(
            $this->raw,
            new SuppressionManifestStore,
            $configuration,
            $this->application()->basePath(),
        );

        $this->application()->instance(ModulesConfig::class, $configuration);
        $this->application()->instance(RawArchitectureCheck::class, $this->raw);
        $this->application()->instance(UnbaselinedArchitectureCheck::class, $suppression);
        $this->application()->instance(
            ArchitectureCheck::class,
            new BaselineArchitectureCheck(
                $suppression,
                new ArchitectureBaselineStore,
                $configuration,
                $this->application()->basePath(),
            ),
        );
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->temporaryPath);

        parent::tearDown();
    }

    public function test_text_json_and_github_outputs_expose_auditable_suppressions(): void
    {
        $this->writeManifest();

        $this->command('module:check --show-suppressions')
            ->expectsOutputToContain('Suppressions: 1 violation suppressed by 1 entry')
            ->expectsOutputToContain('Suppression [matched] cycles MOD-CHECK-001')
            ->expectsOutputToContain('Reason: Legacy cycle tracked by ADR-012.')
            ->expectsOutputToContain('Architecture check passed: 1 rule evaluated')
            ->assertSuccessful();

        [$jsonExitCode, $json] = $this->callCommand('module:check', ['--format' => 'json']);
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(ExitPolicy::SUCCESS, $jsonExitCode);
        self::assertIsArray($payload);
        $suppressions = $payload['suppressions'] ?? null;

        if (! is_array($suppressions)) {
            self::fail('The JSON report must contain suppression audit metadata.');
        }

        $details = $suppressions['details'] ?? null;

        if (! is_array($details) || ! isset($details[0]) || ! is_array($details[0])) {
            self::fail('The JSON report must contain suppression audit details.');
        }

        self::assertSame(1, $suppressions['matched']);
        self::assertSame('matched', $details[0]['status']);
        self::assertSame('Legacy cycle tracked by ADR-012.', $details[0]['reason']);

        [$githubExitCode, $github] = $this->callCommand('module:check', ['--format' => 'github']);

        self::assertSame(ExitPolicy::SUCCESS, $githubExitCode);
        self::assertStringContainsString(
            '::notice title=Moduark architecture suppressions::Suppressions matched 1 violation',
            $github,
        );
    }

    public function test_stale_suppression_is_reported_without_failing_the_check(): void
    {
        $this->writeManifest();
        $this->raw->report = $this->report();

        $this->command('module:check')
            ->expectsOutputToContain('1 stale suppression entry no longer matches an evaluated violation.')
            ->assertSuccessful();
    }

    public function test_malformed_manifest_is_a_check_tool_error(): void
    {
        file_put_contents($this->suppressionPath, '{not-json');

        $this->command('module:check')
            ->expectsOutputToContain('Architecture analysis could not be completed: Suppression manifest')
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    public function test_baseline_generation_excludes_explicitly_suppressed_violations(): void
    {
        $this->writeManifest();

        $this->command('module:baseline')
            ->expectsOutputToContain('Created architecture baseline with 0 violations')
            ->assertSuccessful();

        $baseline = (new ArchitectureBaselineStore)->load($this->baselinePath);

        self::assertInstanceOf(ArchitectureBaseline::class, $baseline);
        self::assertSame(0, $baseline->violationCount());

        [$exitCode, $json] = $this->callCommand('module:check', ['--format' => 'json']);
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(ExitPolicy::SUCCESS, $exitCode);
        self::assertIsArray($payload);
        $suppressions = $payload['suppressions'] ?? null;
        $baselineStatus = $payload['baseline'] ?? null;

        if (! is_array($suppressions) || ! is_array($baselineStatus)) {
            self::fail('The JSON report must preserve suppression and baseline audit metadata.');
        }

        self::assertSame(1, $suppressions['matched']);
        self::assertSame(0, $baselineStatus['violations']);
    }

    /**
     * @param array<string, string> $parameters
     * @return array{int, string}
     */
    private function callCommand(string $command, array $parameters): array
    {
        $output = new BufferedOutput;
        $exitCode = $this->application()->make(Kernel::class)->call($command, $parameters, $output);

        return [$exitCode, trim($output->fetch())];
    }

    private function writeManifest(): void
    {
        file_put_contents($this->suppressionPath, json_encode([
            'schema_version' => 1,
            'suppressions' => [[
                'rule' => 'cycles',
                'code' => 'MOD-CHECK-001',
                'file' => 'app/Modules/Order/OrderModule.php',
                'line' => 12,
                'reason' => 'Legacy cycle tracked by ADR-012.',
            ]],
        ], JSON_THROW_ON_ERROR));
    }

    private function violation(): Violation
    {
        return new Violation(
            RuleId::Cycles,
            'MOD-CHECK-001',
            Severity::Error,
            'Fixture architecture violation.',
            $this->application()->basePath('app/Modules/Order/OrderModule.php'),
            12,
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

final class MutableSuppressionRawCheck implements RawArchitectureCheck
{
    public function __construct(public CheckReport $report)
    {
    }

    public function check(?Level $level = null): CheckReport
    {
        return $this->report;
    }
}

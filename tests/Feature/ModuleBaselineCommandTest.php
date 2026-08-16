<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Analysis\ArchitectureCheck;
use Cluion\Moduark\Analysis\Baseline\ArchitectureBaseline;
use Cluion\Moduark\Analysis\Baseline\ArchitectureBaselineStore;
use Cluion\Moduark\Analysis\Baseline\BaselineArchitectureCheck;
use Cluion\Moduark\Analysis\CheckReport;
use Cluion\Moduark\Analysis\RawArchitectureCheck;
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

final class ModuleBaselineCommandTest extends TestCase
{
    private string $temporaryPath;

    private string $baselinePath;

    private MutableRawArchitectureCheck $raw;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryPath = sys_get_temp_dir().'/moduark-baseline-'.bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->temporaryPath, 0755, true));
        $this->baselinePath = $this->temporaryPath.'/moduark-baseline.json';
        $configuration = ModulesConfig::from(
            [
                'path' => $this->temporaryPath.'/Modules',
                'architecture' => [
                    'level' => 1,
                    'baseline' => $this->baselinePath,
                    'rules' => [],
                ],
            ],
            [],
        );
        $this->raw = new MutableRawArchitectureCheck($this->report($this->violation('Order', 'User')));
        $store = new ArchitectureBaselineStore;

        $this->application()->instance(ModulesConfig::class, $configuration);
        $this->application()->instance(RawArchitectureCheck::class, $this->raw);
        $this->application()->instance(
            ArchitectureCheck::class,
            new BaselineArchitectureCheck(
                $this->raw,
                $store,
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

    public function test_command_creates_a_baseline_and_check_reports_the_match(): void
    {
        $this->command('module:baseline')
            ->expectsOutputToContain('Created architecture baseline with 1 violation')
            ->assertSuccessful();

        self::assertFileExists($this->baselinePath);

        $this->command('module:check')
            ->expectsOutputToContain('Baseline: 1 existing violation matched')
            ->expectsOutputToContain('Architecture check passed: 1 rule evaluated')
            ->assertSuccessful();
    }

    public function test_existing_baseline_requires_force_to_capture_current_debt(): void
    {
        $this->command('module:baseline')->assertSuccessful();

        $this->command('module:baseline')
            ->expectsOutputToContain('already exists')
            ->assertExitCode(ExitPolicy::TOOL_ERROR);

        $this->raw->report = $this->report(
            $this->violation('Order', 'User'),
            $this->violation('Billing', 'Order'),
        );

        $this->command('module:baseline --force')
            ->expectsOutputToContain('Replaced architecture baseline with 2 violations')
            ->assertSuccessful();
    }

    public function test_prune_removes_stale_entries_without_adopting_new_debt(): void
    {
        $this->raw->report = $this->report(
            $this->violation('Order', 'User'),
            $this->violation('User', 'Order'),
        );
        $this->command('module:baseline')->assertSuccessful();
        $this->raw->report = $this->report(
            $this->violation('Order', 'User'),
            $this->violation('Billing', 'Order'),
        );

        $this->command('module:baseline --prune')
            ->expectsOutputToContain('Pruned 1 stale baseline violation')
            ->assertSuccessful();

        $baseline = (new ArchitectureBaselineStore)->load($this->baselinePath);

        self::assertInstanceOf(ArchitectureBaseline::class, $baseline);
        self::assertSame(1, $baseline->violationCount());
        self::assertSame('Order', $baseline->toArray()['violations'][0]['consumer']);
    }

    public function test_json_and_github_outputs_include_baseline_audit_metadata(): void
    {
        $this->command('module:baseline')->assertSuccessful();

        [$jsonExitCode, $json] = $this->callCommand('module:check', ['--format' => 'json']);
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(ExitPolicy::SUCCESS, $jsonExitCode);
        self::assertIsArray($payload);
        self::assertSame([
            'path' => $this->baselinePath,
            'violations' => 1,
            'matched' => 1,
            'stale' => 0,
            'exceeded' => 0,
        ], $payload['baseline']);

        [$githubExitCode, $github] = $this->callCommand('module:check', ['--format' => 'github']);

        self::assertSame(ExitPolicy::SUCCESS, $githubExitCode);
        self::assertStringContainsString(
            '::notice title=Moduark architecture baseline::Baseline matched 1 existing violation',
            $github,
        );
    }

    public function test_invalid_baseline_is_a_check_tool_error(): void
    {
        file_put_contents($this->baselinePath, '{not-json');

        $this->command('module:check')
            ->expectsOutputToContain('Architecture analysis could not be completed: Architecture baseline')
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    public function test_incomplete_raw_report_never_writes_a_baseline(): void
    {
        $complete = $this->report();
        $this->raw->report = new CheckReport(
            $complete->architecture(),
            [],
            [RuleId::DatabaseOwnership],
        );

        $this->command('module:baseline')
            ->expectsOutputToContain('Architecture analysis is incomplete; no baseline was written.')
            ->assertExitCode(ExitPolicy::TOOL_ERROR);

        self::assertFileDoesNotExist($this->baselinePath);
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

    private function violation(string $consumer, string $target): Violation
    {
        return new Violation(
            RuleId::Cycles,
            'MOD-CHECK-001',
            Severity::Error,
            'Fixture architecture violation.',
            $this->temporaryPath.'/app/Modules/'.$consumer.'/'.$consumer.'Module.php',
            12,
            $consumer,
            $target,
            'Tests\\'.$consumer.'FixtureSymbol',
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

final class MutableRawArchitectureCheck implements RawArchitectureCheck
{
    public function __construct(public CheckReport $report)
    {
    }

    public function check(?Level $level = null): CheckReport
    {
        return $this->report;
    }
}

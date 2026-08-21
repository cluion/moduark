<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Analysis\CheckReport;
use Cluion\Moduark\Analysis\Suppression\SuppressionManifest;
use Cluion\Moduark\Architecture\EffectiveArchitecture;
use Cluion\Moduark\Architecture\EffectiveRule;
use Cluion\Moduark\Architecture\EffectiveRules;
use Cluion\Moduark\Architecture\Level;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Architecture\Violation;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ArchitectureSuppressionTest extends TestCase
{
    public function test_exact_file_and_line_scope_only_suppresses_the_matching_violation(): void
    {
        $manifest = $this->manifest([
            'rule' => 'cycles',
            'code' => 'MOD-CHECK-001',
            'file' => 'app/Modules/Order/OrderModule.php',
            'line' => 12,
            'reason' => 'Legacy cycle tracked by ADR-012.',
        ]);
        $report = $this->report(
            [
                new RuleResult(RuleId::Cycles, [
                    $this->violation(12),
                    $this->violation(20),
                ]),
            ],
        );

        $filtered = $manifest->apply($report, '/workspace/moduark-suppressions.json', '/workspace');
        $status = $filtered->suppressions();

        self::assertSame([20], array_map(
            static fn (Violation $violation): ?int => $violation->line(),
            $filtered->violations(),
        ));
        self::assertNotNull($status);
        self::assertSame([
            'path' => 'moduark-suppressions.json',
            'entries' => 1,
            'matched' => 1,
            'stale' => 0,
            'inactive' => 0,
            'details' => [[
                'rule' => 'cycles',
                'code' => 'MOD-CHECK-001',
                'file' => 'app/Modules/Order/OrderModule.php',
                'line' => 12,
                'reason' => 'Legacy cycle tracked by ADR-012.',
                'status' => 'matched',
                'matches' => 1,
            ]],
        ], $status->toArray());
    }

    public function test_audits_stale_and_inactive_entries_separately(): void
    {
        $manifest = $this->manifest(
            [
                'rule' => 'cycles',
                'code' => 'MOD-CHECK-001',
                'file' => 'app/Modules/User/UserModule.php',
                'reason' => 'No longer present.',
            ],
            [
                'rule' => 'internal_api_access',
                'code' => 'MOD-BOUNDARY-001',
                'symbol' => 'App\\Modules\\User\\Internal\\Secret',
                'reason' => 'Only evaluated at a stricter Level.',
            ],
        );

        $filtered = $manifest->apply(
            $this->report([new RuleResult(RuleId::Cycles)]),
            'moduark-suppressions.json',
            '/workspace',
        );
        $status = $filtered->suppressions();

        self::assertNotNull($status);
        self::assertSame(1, $status->stale());
        self::assertSame(1, $status->inactive());
        self::assertSame(['stale', 'inactive'], array_map(
            static fn ($audit): string => $audit->status(),
            $status->details(),
        ));
    }

    public function test_overlapping_suppressions_are_rejected_when_they_match_a_violation(): void
    {
        $manifest = $this->manifest(
            [
                'rule' => 'cycles',
                'code' => 'MOD-CHECK-001',
                'file' => 'app/Modules/Order/OrderModule.php',
                'reason' => 'File-level exception.',
            ],
            [
                'rule' => 'cycles',
                'code' => 'MOD-CHECK-001',
                'symbol' => 'Tests\\FixtureSymbol',
                'reason' => 'Symbol-level exception.',
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('matches overlapping suppressions');

        $manifest->apply(
            $this->report([new RuleResult(RuleId::Cycles, [$this->violation(12)])]),
            'moduark-suppressions.json',
            '/workspace',
        );
    }

    public function test_duplicate_selectors_are_rejected_even_when_reasons_differ(): void
    {
        $entry = [
            'rule' => 'cycles',
            'code' => 'MOD-CHECK-001',
            'file' => 'app/Modules/Order/OrderModule.php',
            'reason' => 'First review record.',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('selectors must be unique');

        $this->manifest($entry, array_replace($entry, ['reason' => 'Second review record.']));
    }

    /**
     * @param array<string, mixed> $entry
     */
    #[DataProvider('invalidEntries')]
    public function test_invalid_or_overly_broad_entries_are_rejected(array $entry, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->manifest($entry);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidEntries(): iterable
    {
        yield 'global ignore' => [[
            'rule' => 'cycles',
            'code' => 'MOD-CHECK-001',
            'reason' => 'Too broad.',
        ], 'global ignores are not allowed'];

        yield 'missing reason' => [[
            'rule' => 'cycles',
            'code' => 'MOD-CHECK-001',
            'file' => 'app/Modules/Order/OrderModule.php',
        ], 'reason must be a non-empty string'];

        yield 'absolute path' => [[
            'rule' => 'cycles',
            'code' => 'MOD-CHECK-001',
            'file' => '/workspace/app/Modules/Order/OrderModule.php',
            'reason' => 'Non-portable.',
        ], 'repository-relative path'];

        yield 'unknown typo' => [[
            'rule' => 'cycles',
            'code' => 'MOD-CHECK-001',
            'file' => 'app/Modules/Order/OrderModule.php',
            'reson' => 'Misspelled.',
            'reason' => 'Known field still present.',
        ], 'unknown field: reson'];

        yield 'undeclared dependency without pair' => [[
            'rule' => 'undeclared_dependencies',
            'code' => 'MOD-DEPENDENCY-002',
            'file' => 'app/Modules/Order/Actions/PlaceOrder.php',
            'symbol' => 'App\\Modules\\User\\Internal\\UserRepository',
            'reason' => 'Pair-scoped diagnostics require a pair-scoped exception.',
        ], 'must select both consumer and target Modules'];
    }

    private function violation(int $line): Violation
    {
        return new Violation(
            RuleId::Cycles,
            'MOD-CHECK-001',
            Severity::Error,
            'Fixture architecture violation.',
            '/workspace/app/Modules/Order/OrderModule.php',
            $line,
            'Order',
            'User',
            'Tests\\FixtureSymbol',
        );
    }

    /**
     * @param list<RuleResult> $results
     */
    private function report(array $results): CheckReport
    {
        return new CheckReport(
            new EffectiveArchitecture(
                Level::Modular,
                Level::Modular,
                new EffectiveRules([
                    new EffectiveRule(RuleId::Cycles, true, Severity::Error),
                ]),
            ),
            $results,
            [],
        );
    }

    /**
     * @param array<string, mixed> ...$entries
     */
    private function manifest(array ...$entries): SuppressionManifest
    {
        return SuppressionManifest::fromArray([
            'schema_version' => 1,
            'suppressions' => $entries,
        ]);
    }
}

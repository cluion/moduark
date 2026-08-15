<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Architecture\Violation;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RuleResultTest extends TestCase
{
    public function test_empty_result_passes(): void
    {
        $result = new RuleResult(RuleId::Cycles);

        self::assertTrue($result->passed());
        self::assertFalse($result->hasErrors());
        self::assertFalse($result->hasWarnings());
        self::assertSame(ExitPolicy::SUCCESS, (new ExitPolicy)->exitCode([$result]));
    }

    /**
     * @param list<Severity> $severities
     */
    #[DataProvider('exitCases')]
    public function test_exit_policy_only_blocks_on_errors(array $severities, int $expected): void
    {
        $violations = [];

        foreach ($severities as $index => $severity) {
            $violations[] = new Violation(
                RuleId::Cycles,
                sprintf('MOD-CYCLE-%03d', $index + 1),
                $severity,
                'A cycle was found.',
                '/app/Modules/Order/OrderModule.php',
                $index + 1,
                'Order',
                'User',
                null,
                'Invert one dependency.',
            );
        }

        $result = new RuleResult(RuleId::Cycles, $violations);

        self::assertSame($severities === [], $result->passed());
        self::assertSame(in_array(Severity::Error, $severities, true), $result->hasErrors());
        self::assertSame(in_array(Severity::Warning, $severities, true), $result->hasWarnings());
        self::assertSame($expected, (new ExitPolicy)->exitCode([$result]));
    }

    /**
     * @return iterable<string, array{list<Severity>, int}>
     */
    public static function exitCases(): iterable
    {
        yield 'no violations' => [[], ExitPolicy::SUCCESS];
        yield 'warning only' => [[Severity::Warning], ExitPolicy::SUCCESS];
        yield 'error only' => [[Severity::Error], ExitPolicy::VIOLATIONS_FOUND];
        yield 'warning and error' => [
            [Severity::Warning, Severity::Error],
            ExitPolicy::VIOLATIONS_FOUND,
        ];
    }

    public function test_result_rejects_a_violation_from_another_rule(): void
    {
        $violation = new Violation(
            RuleId::InternalApiAccess,
            'MOD-BOUNDARY-001',
            Severity::Error,
            'An internal API was accessed.',
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Rule result [cycles] cannot contain a violation from rule [internal_api_access].',
        );

        new RuleResult(RuleId::Cycles, [$violation]);
    }

    #[DataProvider('invalidViolations')]
    public function test_violation_payload_is_validated(
        string $code,
        string $message,
        ?string $file,
        ?int $line,
        string $expectedMessage,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        new Violation(
            RuleId::Cycles,
            $code,
            Severity::Error,
            $message,
            $file,
            $line,
        );
    }

    /**
     * @return iterable<string, array{string, string, ?string, ?int, string}>
     */
    public static function invalidViolations(): iterable
    {
        yield 'invalid code' => ['cycle-1', 'Cycle.', null, null, 'MOD-NAME-000 format'];
        yield 'empty message' => ['MOD-CYCLE-001', ' ', null, null, 'message must not be empty'];
        yield 'empty file' => ['MOD-CYCLE-001', 'Cycle.', ' ', null, 'file must not be empty'];
        yield 'line without file' => [
            'MOD-CYCLE-001',
            'Cycle.',
            null,
            10,
            'line must be positive and accompanied by a file',
        ];
        yield 'non-positive line' => [
            'MOD-CYCLE-001',
            'Cycle.',
            '/file.php',
            0,
            'line must be positive and accompanied by a file',
        ];
    }

    public function test_violation_export_preserves_diagnostic_context(): void
    {
        $violation = new Violation(
            RuleId::MissingDependencies,
            'MOD-DEPENDENCY-001',
            Severity::Error,
            'The User Module is missing.',
            '/app/Modules/Order/OrderModule.php',
            12,
            'Order',
            'User',
            'UserModule',
            'Declare or enable the User Module.',
        );

        self::assertSame([
            'rule' => 'missing_dependencies',
            'code' => 'MOD-DEPENDENCY-001',
            'severity' => 'error',
            'message' => 'The User Module is missing.',
            'file' => '/app/Modules/Order/OrderModule.php',
            'line' => 12,
            'consumer' => 'Order',
            'target' => 'User',
            'symbol' => 'UserModule',
            'suggestion' => 'Declare or enable the User Module.',
        ], $violation->toArray());
    }
}

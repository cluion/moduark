<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Compatibility\DependencyMatrixRunner;

final class DependencyMatrixRunnerTest extends TestCase
{
    public function test_default_selection_contains_the_four_supported_cases(): void
    {
        self::assertSame([
            'laravel-12-lowest',
            'laravel-12-highest',
            'laravel-13-lowest',
            'laravel-13-highest',
        ], DependencyMatrixRunner::parseCases(false));
    }

    public function test_requested_cases_are_deduplicated_in_order(): void
    {
        self::assertSame([
            'laravel-13-highest',
            'laravel-12-lowest',
        ], DependencyMatrixRunner::parseCases(
            'laravel-13-highest,laravel-12-lowest,laravel-13-highest',
        ));
    }

    #[DataProvider('invalidCases')]
    public function test_invalid_case_options_are_rejected(mixed $value): void
    {
        $this->expectException(RuntimeException::class);

        DependencyMatrixRunner::parseCases($value);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidCases(): iterable
    {
        yield 'unsupported case' => ['laravel-11-lowest'];
        yield 'empty' => [''];
        yield 'space' => ['laravel-12-lowest, laravel-13-lowest'];
        yield 'not a string' => [12];
    }

    public function test_case_metadata_matches_the_supported_php_floors(): void
    {
        $cases = DependencyMatrixRunner::cases();

        self::assertSame('8.2.0', $cases['laravel-12-lowest']['php']);
        self::assertSame('8.3.0', $cases['laravel-13-lowest']['php']);
        self::assertSame('^10.0', $cases['laravel-12-highest']['testbench']);
        self::assertSame('^11.0', $cases['laravel-13-highest']['testbench']);
    }

    public function test_package_path_must_contain_composer_metadata(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Moduark package path [/missing/moduark] is invalid.');

        new DependencyMatrixRunner('/missing/moduark');
    }
}

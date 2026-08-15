<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Installation\CleanApplicationRunner;

final class CleanApplicationRunnerTest extends TestCase
{
    public function test_default_matrix_contains_laravel_twelve_and_thirteen(): void
    {
        self::assertSame([12, 13], CleanApplicationRunner::parseMajors(false));
    }

    public function test_requested_majors_are_deduplicated_in_order(): void
    {
        self::assertSame([13, 12], CleanApplicationRunner::parseMajors('13,12,13'));
    }

    #[DataProvider('invalidMajors')]
    public function test_invalid_major_options_are_rejected(mixed $value): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The --laravel option must contain 12, 13, or 12,13.');

        CleanApplicationRunner::parseMajors($value);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidMajors(): iterable
    {
        yield 'unsupported major' => ['11'];
        yield 'empty' => [''];
        yield 'space' => ['12, 13'];
        yield 'not a string' => [12];
    }

    public function test_package_path_must_contain_composer_metadata(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Moduark package path [/missing/moduark] is invalid.');

        new CleanApplicationRunner('/missing/moduark');
    }
}

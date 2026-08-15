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

    public function test_package_version_defaults_to_the_current_checkout(): void
    {
        self::assertNull(CleanApplicationRunner::parsePackageVersion(false));
    }

    public function test_exact_published_package_version_is_accepted(): void
    {
        self::assertSame(
            '0.2.0-beta.2',
            CleanApplicationRunner::parsePackageVersion('0.2.0-beta.2'),
        );
        self::assertSame('1.0.0', CleanApplicationRunner::parsePackageVersion('1.0.0'));
    }

    #[DataProvider('invalidPackageVersions')]
    public function test_non_exact_package_versions_are_rejected(mixed $value): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The --package option must be an exact stable or pre-release version.',
        );

        CleanApplicationRunner::parsePackageVersion($value);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidPackageVersions(): iterable
    {
        yield 'empty' => [''];
        yield 'range' => ['^0.2@beta'];
        yield 'branch' => ['dev-main'];
        yield 'tag prefix' => ['v0.2.0-beta.2'];
        yield 'space' => ['0.2.0 beta.2'];
        yield 'not a string' => [2];
    }

    public function test_package_path_must_contain_composer_metadata(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Moduark package path [/missing/moduark] is invalid.');

        new CleanApplicationRunner('/missing/moduark');
    }
}

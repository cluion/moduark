<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Export\ExportTargetMapping;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExportTargetMappingTest extends TestCase
{
    public function test_it_parses_an_explicit_module_target_mapping(): void
    {
        $mapping = ExportTargetMapping::fromString('User=packages/user-module');

        self::assertSame('User', $mapping->module());
        self::assertSame('packages/user-module', $mapping->target());
    }

    #[DataProvider('invalidMappings')]
    public function test_it_rejects_ambiguous_mapping_syntax(string $mapping): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('expected Module=portable/path');

        ExportTargetMapping::fromString($mapping);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidMappings(): iterable
    {
        yield 'leading whitespace' => [' User=packages/user'];
        yield 'missing module' => ['=packages/user'];
        yield 'invalid module' => ['user-module=packages/user'];
        yield 'missing target' => ['User='];
        yield 'multiple separators' => ['User=packages=user'];
    }
}

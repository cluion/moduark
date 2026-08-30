<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Export\ExportDependencyMapping;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExportDependencyMappingTest extends TestCase
{
    public function test_it_parses_an_explicit_module_package_constraint_mapping(): void
    {
        $mapping = ExportDependencyMapping::fromString(
            'User=acme/user-module:^1.2 || ^2.0=>Acme\\UserModule',
        );

        self::assertSame('User', $mapping->module());
        self::assertSame('acme/user-module', $mapping->package());
        self::assertSame('^1.2 || ^2.0', $mapping->constraint());
        self::assertSame('Acme\\UserModule', $mapping->namespace());
    }

    #[DataProvider('invalidMappings')]
    public function test_it_rejects_malformed_or_invalid_mappings(string $mapping): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('expected Module=vendor/package:constraint=>Namespace');

        ExportDependencyMapping::fromString($mapping);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidMappings(): iterable
    {
        yield 'empty' => [''];
        yield 'surrounding whitespace' => [' User=acme/user-module:^1.0=>Acme\\UserModule'];
        yield 'missing module' => ['=acme/user-module:^1.0=>Acme\\UserModule'];
        yield 'invalid module' => ['User-Module=acme/user-module:^1.0=>Acme\\UserModule'];
        yield 'uppercase package' => ['User=Acme/user-module:^1.0=>Acme\\UserModule'];
        yield 'missing constraint' => ['User=acme/user-module=>Acme\\UserModule'];
        yield 'invalid constraint' => ['User=acme/user-module:definitely not a constraint=>Acme\\UserModule'];
        yield 'missing namespace' => ['User=acme/user-module:^1.0=>'];
        yield 'invalid namespace' => ['User=acme/user-module:^1.0=>Acme/UserModule'];
    }
}

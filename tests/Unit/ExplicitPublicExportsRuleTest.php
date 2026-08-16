<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\Rules\ExplicitPublicExportsRule;
use Cluion\Moduark\Analysis\Source\SourceIndex;
use Cluion\Moduark\Analysis\Source\SourceReference;
use Cluion\Moduark\Analysis\Source\SourceSymbol;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ExplicitPublicExportsRuleTest extends TestCase
{
    public function test_unexported_missing_and_foreign_owned_exports_are_reported(): void
    {
        $result = (new ExplicitPublicExportsRule)->inspect(
            $this->context(),
            RuleId::ExplicitPublicExports->defaultSeverity(),
        );

        self::assertFalse($result->passed());
        self::assertTrue($result->hasErrors());
        self::assertCount(3, $result->violations());
        $violations = [];

        foreach ($result->violations() as $violation) {
            $violations[$violation->code()] = $violation;
        }

        self::assertSame([
            'rule' => 'explicit_public_exports',
            'code' => 'MOD-EXPORT-001',
            'severity' => 'error',
            'message' => 'Module [Order] accesses [Tests\\Unit\\ExportUserInternal], but Module [User] does not explicitly export it.',
            'file' => '/modules/Order/Actions/ReadUser.php',
            'line' => 11,
            'consumer' => 'Order',
            'target' => 'User',
            'symbol' => ExportUserInternal::class,
            'suggestion' => 'Add Tests\\Unit\\ExportUserInternal::class to ExportUserModule::exports(), or consume a symbol that Module [User] explicitly exports.',
        ], $violations['MOD-EXPORT-001']->toArray());
        self::assertSame(DateTimeImmutable::class, $violations['MOD-EXPORT-002']->symbol());
        self::assertSame('Order', $violations['MOD-EXPORT-002']->consumer());
        self::assertSame(ExportUserPublic::class, $violations['MOD-EXPORT-003']->symbol());
        self::assertSame('User', $violations['MOD-EXPORT-003']->target());
    }

    public function test_module_entry_and_explicit_cross_module_references_pass(): void
    {
        $result = (new ExplicitPublicExportsRule)->inspect(
            $this->context(includeInvalidExports: false, includeUnexportedReference: false),
            RuleId::ExplicitPublicExports->defaultSeverity(),
        );

        self::assertTrue($result->passed());
        self::assertSame([], $result->violations());
    }

    private function context(
        bool $includeInvalidExports = true,
        bool $includeUnexportedReference = true,
    ): AnalysisContext {
        $registry = new ModuleRegistry([
            $this->module('Order', ExportOrderModule::class),
            $this->module('User', ExportUserModule::class),
        ]);
        $symbols = [
            $this->symbol(ExportOrderModule::class, ExportOrderModule::class, '/modules/Order/OrderModule.php'),
            $this->symbol(ExportUserModule::class, ExportUserModule::class, '/modules/User/UserModule.php'),
            $this->symbol(ExportUserPublic::class, ExportUserModule::class, '/modules/User/Contracts/ExportUserPublic.php'),
            $this->symbol(ExportUserInternal::class, ExportUserModule::class, '/modules/User/Contracts/ExportUserInternal.php'),
        ];
        $references = [
            new SourceReference(
                ExportOrderModule::class,
                ExportUserModule::class,
                ExportUserPublic::class,
                '/modules/Order/Actions/ReadUser.php',
                10,
            ),
            new SourceReference(
                ExportOrderModule::class,
                ExportUserModule::class,
                ExportUserModule::class,
                '/modules/Order/OrderModule.php',
                5,
            ),
        ];

        if ($includeUnexportedReference) {
            $references[] = new SourceReference(
                ExportOrderModule::class,
                ExportUserModule::class,
                ExportUserInternal::class,
                '/modules/Order/Actions/ReadUser.php',
                11,
            );
        }

        return new AnalysisContext($registry, [
            new ModuleDescriptor(
                ExportOrderModule::class,
                [ExportUserModule::class],
                [],
                exports: $includeInvalidExports
                    ? [DateTimeImmutable::class, ExportUserPublic::class]
                    : [],
            ),
            new ModuleDescriptor(
                ExportUserModule::class,
                [],
                [],
                exports: [ExportUserPublic::class],
            ),
        ], new SourceIndex($symbols, $references));
    }

    /** @param class-string<Module> $owner */
    private function symbol(string $name, string $owner, string $file): SourceSymbol
    {
        return new SourceSymbol($name, $owner, $file, 3);
    }

    /** @param class-string<Module> $module */
    private function module(string $name, string $module): DiscoveredModule
    {
        return new DiscoveredModule(
            $name,
            $module,
            "/modules/{$name}/{$name}Module.php",
            __NAMESPACE__,
        );
    }
}

final class ExportOrderModule extends Module
{
}

final class ExportUserModule extends Module
{
}

final class ExportUserPublic
{
}

final class ExportUserInternal
{
}

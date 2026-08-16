<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Exceptions\InvalidModuleMetadata;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Module;
use PHPUnit\Framework\TestCase;

final class ExplicitExportsMetadataTest extends TestCase
{
    public function test_default_exports_are_empty(): void
    {
        $descriptor = (new ModuleMetadataCompiler)->compile(EmptyExportsModule::class);

        self::assertSame([], $descriptor->exports());
        self::assertSame([], $descriptor->toArray()['exports']);
    }

    public function test_class_interface_trait_and_enum_exports_are_compiled_deterministically(): void
    {
        $descriptor = (new ModuleMetadataCompiler)->compile(ValidExportsModule::class);

        self::assertSame([
            ExportedClass::class,
            ExportedContract::class,
            ExportedTrait::class,
            ExportedStatus::class,
        ], $descriptor->exports());
        self::assertSame(
            $descriptor->toArray(),
            ModuleDescriptor::fromArray($descriptor->toArray())->toArray(),
        );
    }

    public function test_duplicate_exports_are_rejected(): void
    {
        $this->expectException(InvalidModuleMetadata::class);
        $this->expectExceptionMessage(
            DuplicateExportModule::class
                .'::exports() contains duplicate reference ['.ExportedContract::class.'].',
        );

        (new ModuleMetadataCompiler)->compile(DuplicateExportModule::class);
    }
}

final class EmptyExportsModule extends Module
{
}

final class ValidExportsModule extends Module
{
    public function exports(): array
    {
        return [
            ExportedClass::class,
            ExportedContract::class,
            ExportedTrait::class,
            ExportedStatus::class,
        ];
    }
}

final class DuplicateExportModule extends Module
{
    public function exports(): array
    {
        return [
            ExportedContract::class,
            ExportedContract::class,
        ];
    }
}

final class ExportedClass
{
    use ExportedTrait;
}

interface ExportedContract
{
}

trait ExportedTrait
{
}

enum ExportedStatus
{
    case Active;
}

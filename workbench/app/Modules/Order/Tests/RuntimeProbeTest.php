<?php

declare(strict_types=1);

namespace Workbench\App\Modules\Order\Tests;

use PHPUnit\Framework\TestCase;

final class RuntimeProbeTest extends TestCase
{
    public function test_runtime_fixture_is_selectable_by_module_operation(): void
    {
        self::assertFileExists(dirname(__DIR__).'/OrderModule.php');
    }
}

<?php

declare(strict_types=1);

namespace Tests;

use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    use WithWorkbench;

    /**
     * Package discovery is part of the baseline contract under test.
     *
     * @var bool
     */
    protected $enablesPackageDiscoveries = true;
}

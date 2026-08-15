<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Testing\PendingCommand;
use LogicException;
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

    protected function application(): Application
    {
        if (! $this->app instanceof Application) {
            throw new LogicException('The Testbench application has not been created.');
        }

        return $this->app;
    }

    protected function command(string $command): PendingCommand
    {
        $pending = $this->artisan($command);

        if (! $pending instanceof PendingCommand) {
            throw new LogicException("The [{$command}] command did not return a pending test command.");
        }

        return $pending;
    }
}

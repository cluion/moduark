<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class ModuleListCommandTest extends TestCase
{
    public function test_it_lists_modules_in_deterministic_order(): void
    {
        $this->expectModuleTable('moduark:list');
    }

    public function test_it_lists_the_same_modules_after_config_cache(): void
    {
        try {
            $this->command('config:cache')->assertSuccessful();
            $this->refreshApplication();

            $this->expectModuleTable('moduark:list');
        } finally {
            $this->command('config:clear')->run();
        }
    }

    private function expectModuleTable(string $command): void
    {
        $this->command($command)
            ->expectsTable(
                ['Module', 'State', 'Level', 'Dependencies', 'Requires', 'Provides'],
                [
                    ['Order', 'enabled', 1, 'User', '—', '—'],
                    ['User', 'enabled', 1, '—', '—', '—'],
                    ['Workbench', 'enabled', 1, '—', '—', '—'],
                ],
            )
            ->assertSuccessful();
    }
}

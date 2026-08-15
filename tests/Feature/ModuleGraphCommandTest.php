<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Architecture\ExitPolicy;
use Tests\TestCase;

final class ModuleGraphCommandTest extends TestCase
{
    public function test_it_displays_the_module_dependency_graph_as_text(): void
    {
        $this->expectWorkbenchTextGraph('module:graph');
    }

    public function test_it_displays_the_module_dependency_graph_as_mermaid(): void
    {
        $this->command('module:graph --format=mermaid')
            ->expectsOutputToContain('flowchart LR')
            ->expectsOutputToContain('M0["Order"]')
            ->expectsOutputToContain('M1["User"]')
            ->expectsOutputToContain('M2["Workbench"]')
            ->expectsOutputToContain('M0 --> M1')
            ->assertSuccessful();
    }

    public function test_it_limits_output_to_a_module_and_direct_neighbors(): void
    {
        $this->command('module:graph User')
            ->expectsOutputToContain('Order -> User')
            ->expectsOutputToContain('User -> —')
            ->doesntExpectOutputToContain('Workbench')
            ->assertSuccessful();
    }

    public function test_unknown_module_is_a_tool_error(): void
    {
        $this->command('module:graph Unknown')
            ->expectsOutputToContain(
                'Module graph could not be generated: Module [Unknown] was not found in the dependency graph.',
            )
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    public function test_unknown_output_format_is_a_tool_error(): void
    {
        $this->command('module:graph --format=json')
            ->expectsOutputToContain('The --format option must be text or mermaid.')
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    public function test_it_displays_the_same_graph_after_config_cache(): void
    {
        try {
            $this->command('config:cache')->assertSuccessful();
            $this->refreshApplication();

            $this->expectWorkbenchTextGraph('module:graph');
        } finally {
            $this->command('config:clear')->run();
        }
    }

    private function expectWorkbenchTextGraph(string $command): void
    {
        $this->command($command)
            ->expectsOutputToContain('Order -> User')
            ->expectsOutputToContain('User -> —')
            ->expectsOutputToContain('Workbench -> —')
            ->assertSuccessful();
    }
}

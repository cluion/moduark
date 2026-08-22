<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Graph\CapabilityGraphBuilder;
use Cluion\Moduark\Graph\CombinedGraphBuilder;
use Cluion\Moduark\Graph\Export\MermaidCapabilityGraphExporter;
use Cluion\Moduark\Graph\Export\MermaidCombinedGraphExporter;
use Cluion\Moduark\Graph\Export\MermaidModuleGraphExporter;
use Cluion\Moduark\Graph\Export\TextCapabilityGraphExporter;
use Cluion\Moduark\Graph\Export\TextCombinedGraphExporter;
use Cluion\Moduark\Graph\Export\TextModuleGraphExporter;
use Cluion\Moduark\Graph\ModuleGraphBuilder;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

final class ModuleGraphCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'moduark:graph
        {module? : Limit the graph to a Module neighborhood}
        {--view=module : Graph view (module, capability, or combined)}
        {--format=text : Output format (text or mermaid)}';

    /**
     * @var string
     */
    protected $description = 'Display a Module architecture graph';

    public function __construct(
        private readonly ModuleGraphBuilder $moduleBuilder,
        private readonly CapabilityGraphBuilder $capabilityBuilder,
        private readonly CombinedGraphBuilder $combinedBuilder,
        private readonly TextModuleGraphExporter $moduleText,
        private readonly MermaidModuleGraphExporter $moduleMermaid,
        private readonly TextCapabilityGraphExporter $capabilityText,
        private readonly MermaidCapabilityGraphExporter $capabilityMermaid,
        private readonly TextCombinedGraphExporter $combinedText,
        private readonly MermaidCombinedGraphExporter $combinedMermaid,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $format = $this->option('format');

        if (! is_string($format) || ! in_array($format, ['text', 'mermaid'], true)) {
            $this->components->error('The --format option must be text or mermaid.');

            return ExitPolicy::TOOL_ERROR;
        }

        $view = $this->option('view');

        if (! is_string($view)
            || ! in_array($view, ['module', 'capability', 'combined'], true)) {
            $this->components->error(
                'The --view option must be module, capability, or combined.',
            );

            return ExitPolicy::TOOL_ERROR;
        }

        try {
            $module = $this->argument('module');

            if ($module !== null && ! is_string($module)) {
                throw new InvalidArgumentException('The Module argument must be a string.');
            }

            if ($view === 'module') {
                $graph = $this->moduleBuilder->build();

                if ($module !== null) {
                    $graph = $graph->neighborhood($module);
                }

                if ($graph->discoveredNodes() === []) {
                    $this->components->info('No Modules discovered.');

                    return self::SUCCESS;
                }

                $output = $format === 'text'
                    ? $this->moduleText->export($graph)
                    : $this->moduleMermaid->export($graph);
            } elseif ($view === 'capability') {
                $graph = $this->capabilityBuilder->build();

                if ($module !== null) {
                    $graph = $graph->neighborhood($module);
                }

                if ($graph->modules() === []) {
                    $this->components->info('No Modules discovered.');

                    return self::SUCCESS;
                }

                $output = $format === 'text'
                    ? $this->capabilityText->export($graph)
                    : $this->capabilityMermaid->export($graph);
            } else {
                $graph = $this->combinedBuilder->build();

                if ($module !== null) {
                    $graph = $graph->neighborhood($module);
                }

                if ($graph->moduleGraph()->discoveredNodes() === []) {
                    $this->components->info('No Modules discovered.');

                    return self::SUCCESS;
                }

                $output = $format === 'text'
                    ? $this->combinedText->export($graph)
                    : $this->combinedMermaid->export($graph);
            }
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->components->error(
                'Module graph could not be generated: '.$exception->getMessage(),
            );

            return ExitPolicy::TOOL_ERROR;
        }

        foreach (explode(PHP_EOL, $output) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }
}

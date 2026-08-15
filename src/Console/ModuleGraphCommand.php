<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Graph\Export\MermaidModuleGraphExporter;
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
    protected $signature = 'module:graph
        {module? : Limit the graph to a Module and its direct neighbors}
        {--format=text : Output format (text or mermaid)}';

    /**
     * @var string
     */
    protected $description = 'Display the Module dependency graph';

    public function __construct(
        private readonly ModuleGraphBuilder $builder,
        private readonly TextModuleGraphExporter $text,
        private readonly MermaidModuleGraphExporter $mermaid,
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

        try {
            $graph = $this->builder->build();
            $module = $this->argument('module');

            if ($module !== null) {
                if (! is_string($module)) {
                    throw new InvalidArgumentException('The Module argument must be a string.');
                }

                $graph = $graph->neighborhood($module);
            }
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->components->error(
                'Module graph could not be generated: '.$exception->getMessage(),
            );

            return ExitPolicy::TOOL_ERROR;
        }

        if ($graph->discoveredNodes() === []) {
            $this->components->info('No Modules discovered.');

            return self::SUCCESS;
        }

        $output = $format === 'text'
            ? $this->text->export($graph)
            : $this->mermaid->export($graph);

        foreach (explode(PHP_EOL, $output) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }
}

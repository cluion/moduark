<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Generation\NativeGeneratorBridgePlanner;
use Cluion\Moduark\Generation\NativeGeneratorBridgePlanExporter;
use Illuminate\Console\Command;
use JsonException;
use Symfony\Component\Console\Output\OutputInterface;

final class NativeGeneratorBridgeCommand extends Command
{
    /** @var string */
    protected $signature = 'moduark:native-bridge
        {--format=text : Plan output format (text or json)}';

    /** @var string */
    protected $description = 'Inspect the opt-in native Laravel Maker bridge plan';

    public function __construct(
        private readonly NativeGeneratorBridgePlanner $planner,
        private readonly NativeGeneratorBridgePlanExporter $exporter,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $format = $this->option('format');

        if (! is_string($format) || ! in_array($format, ['text', 'json'], true)) {
            $this->components->error('The native bridge output format must be text or json.');

            return ExitPolicy::TOOL_ERROR;
        }

        $plan = $this->planner->plan();

        if ($format === 'json') {
            try {
                $this->output->write($this->exporter->json($plan), false, OutputInterface::OUTPUT_RAW);
            } catch (JsonException $exception) {
                $this->components->error('Unable to encode the native bridge plan: '.$exception->getMessage());

                return ExitPolicy::TOOL_ERROR;
            }

            return $plan->exitCode();
        }

        foreach ($this->exporter->textLines($plan) as $line) {
            $this->line($line);
        }

        return $plan->exitCode();
    }
}

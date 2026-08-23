<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Resources\ResourceInspector;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

final class ModuleResourcesCommand extends Command
{
    /** @var string */
    protected $signature = 'moduark:resources
        {module? : Module name to inspect}
        {--format=text : Output format: text or json}';

    /** @var string */
    protected $description = 'List the canonical runtime resource manifest';

    public function __construct(private readonly ResourceInspector $inspector)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $module = $this->module();
        $format = $this->option('format');

        if (! is_string($format) || ! in_array($format, ['text', 'json'], true)) {
            return $this->failOutput($format, 'The resource output format must be text or json.');
        }

        try {
            $modules = $this->inspector->moduleSummaries($module);
            $resources = $this->inspector->resources($module);
            $collisions = $this->inspector->collisions($module);
        } catch (InvalidArgumentException $exception) {
            return $this->failOutput($format, $exception->getMessage());
        }

        $exitCode = $collisions === [] ? ExitPolicy::SUCCESS : ExitPolicy::VIOLATIONS_FOUND;

        if ($format === 'json') {
            return $this->json([
                'schema_version' => 1,
                'status' => $exitCode === ExitPolicy::SUCCESS ? 'passed' : 'collisions_found',
                'cached' => $this->inspector->cached(),
                'modules' => $modules,
                'resources' => $resources,
                'collisions' => $collisions,
                'exit_code' => $exitCode,
                'error' => null,
            ], $exitCode);
        }

        if ($resources === []) {
            $this->components->info('No Module resources discovered.');
        } else {
            $this->table(
                ['Module', 'Plugin', 'Identity', 'Source', 'Namespace', 'Cached'],
                array_map(static fn (array $resource): array => [
                    $resource['module'],
                    $resource['plugin'],
                    $resource['identity'],
                    $resource['source'] ?? '—',
                    $resource['namespace'] ?? '—',
                    $resource['cached'] ? 'yes' : 'no',
                ], $resources),
            );
        }

        if ($collisions !== []) {
            $this->components->error(count($collisions).' resource collision(s) found.');
        }

        return $exitCode;
    }

    private function module(): ?string
    {
        $module = $this->argument('module');

        return is_string($module) && trim($module) !== '' ? $module : null;
    }

    private function failOutput(mixed $format, string $message): int
    {
        if ($format === 'json') {
            return $this->json([
                'schema_version' => 1,
                'status' => 'error',
                'cached' => $this->inspector->cached(),
                'modules' => [],
                'resources' => [],
                'collisions' => [],
                'exit_code' => ExitPolicy::TOOL_ERROR,
                'error' => $message,
            ], ExitPolicy::TOOL_ERROR);
        }

        $this->components->error($message);

        return ExitPolicy::TOOL_ERROR;
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload, int $exitCode): int
    {
        try {
            $this->line(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } catch (JsonException $exception) {
            $this->components->error('Resource output could not be encoded: '.$exception->getMessage());

            return ExitPolicy::TOOL_ERROR;
        }

        return $exitCode;
    }
}

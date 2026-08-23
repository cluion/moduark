<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Resources\ModuleOperationResolver;
use Cluion\Moduark\Resources\ResourceDescriptor;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

final class ModuleSeedCommand extends Command
{
    /** @var string */
    protected $signature = 'moduark:seed
        {module : Module name to seed}
        {--format=text : Output format: text or json}';

    /** @var string */
    protected $description = 'Run seeders owned by one active Module';

    public function __construct(private readonly ModuleOperationResolver $operations)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $module = $this->argument('module');
        $format = $this->option('format');

        if (! is_string($module) || trim($module) === '') {
            return $this->failure($format, 'The module argument must be a non-empty Module name.');
        }

        if (! is_string($format) || ! in_array($format, ['text', 'json'], true)) {
            return $this->failure($format, 'The seed output format must be text or json.');
        }

        try {
            $resources = $this->operations->resources($module, 'seeders');
        } catch (InvalidArgumentException $exception) {
            return $this->failure($format, $exception->getMessage());
        }

        $classes = [];

        foreach ($resources as $resource) {
            $class = $resource->attributes()['class'] ?? null;

            if (is_string($class)) {
                $classes[] = $class;
            }
        }

        foreach ($classes as $class) {
            $exitCode = $format === 'json'
                ? $this->callSilent('db:seed', ['--class' => $class, '--force' => true])
                : $this->call('db:seed', ['--class' => $class, '--force' => true]);

            if ($exitCode !== ExitPolicy::SUCCESS) {
                return $this->failure($format, "Module [{$module}] seeder [{$class}] failed.", $module, $classes);
            }
        }

        $message = $classes === []
            ? 'No Module seeders discovered.'
            : 'Module seeders completed successfully.';

        if ($format === 'json') {
            return $this->json('passed', $module, $classes, ExitPolicy::SUCCESS, null);
        }

        $this->components->info($message);

        return ExitPolicy::SUCCESS;
    }

    /** @param list<string> $classes */
    private function failure(
        mixed $format,
        string $message,
        ?string $module = null,
        array $classes = [],
    ): int {
        if ($format === 'json') {
            return $this->json('error', $module, $classes, ExitPolicy::TOOL_ERROR, $message);
        }

        $this->components->error($message);

        return ExitPolicy::TOOL_ERROR;
    }

    /** @param list<string> $classes */
    private function json(
        string $status,
        ?string $module,
        array $classes,
        int $exitCode,
        ?string $error,
    ): int {
        try {
            $this->line(json_encode([
                'schema_version' => 1,
                'status' => $status,
                'operation' => 'seed',
                'module' => $module,
                'classes' => $classes,
                'exit_code' => $exitCode,
                'error' => $error,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } catch (JsonException $exception) {
            $this->components->error('Seeder output could not be encoded: '.$exception->getMessage());

            return ExitPolicy::TOOL_ERROR;
        }

        return $exitCode;
    }
}

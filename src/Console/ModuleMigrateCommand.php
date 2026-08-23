<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Resources\ModuleOperationResolver;
use Cluion\Moduark\Resources\ResourceDescriptor;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

final class ModuleMigrateCommand extends Command
{
    /** @var string */
    protected $signature = 'moduark:migrate
        {module : Module name to migrate}
        {--format=text : Output format: text or json}';

    /** @var string */
    protected $description = 'Run forward migrations owned by one active Module';

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
            return $this->failure($format, 'The migrate output format must be text or json.');
        }

        try {
            $resources = $this->operations->resources($module, 'migrations');
        } catch (InvalidArgumentException $exception) {
            return $this->failure($format, $exception->getMessage());
        }

        $paths = array_values(array_filter(array_map(
            static fn (ResourceDescriptor $resource): ?string => $resource->sourcePath(),
            $resources,
        )));

        if ($paths === []) {
            return $this->success($format, $module, [], 'No Module migrations discovered.');
        }

        $exitCode = $format === 'json'
            ? $this->callSilent('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true])
            : $this->call('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true]);

        if ($exitCode !== ExitPolicy::SUCCESS) {
            return $this->failure($format, "Module [{$module}] migration failed.", $module, $paths);
        }

        return $this->success(
            $format,
            $module,
            $paths,
            'Module migrations completed successfully.',
        );
    }

    /** @param list<string> $paths */
    private function success(mixed $format, string $module, array $paths, string $message): int
    {
        if ($format === 'json') {
            return $this->json('passed', $module, $paths, ExitPolicy::SUCCESS, null);
        }

        $this->components->info($message);

        return ExitPolicy::SUCCESS;
    }

    /** @param list<string> $paths */
    private function failure(
        mixed $format,
        string $message,
        ?string $module = null,
        array $paths = [],
    ): int {
        if ($format === 'json') {
            return $this->json('error', $module, $paths, ExitPolicy::TOOL_ERROR, $message);
        }

        $this->components->error($message);

        return ExitPolicy::TOOL_ERROR;
    }

    /** @param list<string> $paths */
    private function json(
        string $status,
        ?string $module,
        array $paths,
        int $exitCode,
        ?string $error,
    ): int {
        try {
            $this->line(json_encode([
                'schema_version' => 1,
                'status' => $status,
                'operation' => 'migrate',
                'module' => $module,
                'paths' => $paths,
                'exit_code' => $exitCode,
                'error' => $error,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } catch (JsonException $exception) {
            $this->components->error('Migration output could not be encoded: '.$exception->getMessage());

            return ExitPolicy::TOOL_ERROR;
        }

        return $exitCode;
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Console;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Resources\ModuleOperationResolver;
use Cluion\Moduark\Resources\ResourceDescriptor;
use Composer\InstalledVersions;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\Process\Process;

final class ModuleTestCommand extends Command
{
    /** @var string */
    protected $signature = 'moduark:test
        {module : Module name to test}
        {arguments?* : Arguments forwarded to the selected test runner}
        {--runner=auto : Test runner: auto, phpunit, or pest}
        {--list : List selected paths without running tests}
        {--format=text : Output format: text or json}';

    /** @var string */
    protected $description = 'Run PHPUnit or Pest for one active Module';

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
            return $this->failure($format, 'The test output format must be text or json.');
        }

        try {
            $resources = $this->operations->resources($module, 'tests');
        } catch (InvalidArgumentException $exception) {
            return $this->failure($format, $exception->getMessage());
        }

        $paths = array_values(array_filter(array_map(
            static fn (ResourceDescriptor $resource): ?string => $resource->sourcePath(),
            $resources,
        )));

        if ($paths === []) {
            return $this->result(
                $format,
                'no_tests',
                $module,
                null,
                [],
                '',
                ExitPolicy::VIOLATIONS_FOUND,
                null,
            );
        }

        $runnerOption = $this->option('runner');
        $runner = $this->runner(is_string($runnerOption) ? $runnerOption : '');

        if ($runner === null) {
            return $this->failure($format, 'The test runner must be auto, phpunit, or pest.', $module, $paths);
        }

        if ($this->option('list') === true) {
            return $this->result($format, 'listed', $module, $runner, $paths, '', ExitPolicy::SUCCESS, null);
        }

        $arguments = $this->argument('arguments');

        if (! is_array($arguments) || ! array_is_list($arguments)) {
            return $this->failure($format, 'Test runner arguments are invalid.', $module, $paths);
        }

        $forwarded = array_values(array_filter($arguments, 'is_string'));
        $projectPath = $this->projectPath();
        $binary = $this->projectPath('vendor/bin/'.$runner);

        if (! is_file($binary)) {
            return $this->failure($format, "The [{$runner}] test runner is not installed.", $module, $paths);
        }

        $process = new Process([$binary, ...$forwarded, ...$paths], $projectPath);
        $process->setTimeout(null);
        $output = '';
        $process->run(static function (string $type, string $buffer) use (&$output): void {
            $output .= $buffer;
        });
        $exitCode = $process->isSuccessful()
            ? ExitPolicy::SUCCESS
            : ExitPolicy::VIOLATIONS_FOUND;

        return $this->result(
            $format,
            $exitCode === ExitPolicy::SUCCESS ? 'passed' : 'failed',
            $module,
            $runner,
            $paths,
            $output,
            $exitCode,
            null,
        );
    }

    private function runner(string $requested): ?string
    {
        if ($requested === 'auto') {
            return is_file($this->projectPath('vendor/bin/pest')) ? 'pest' : 'phpunit';
        }

        return in_array($requested, ['phpunit', 'pest'], true) ? $requested : null;
    }

    private function projectPath(string $path = ''): string
    {
        $root = rtrim(InstalledVersions::getRootPackage()['install_path'], '/\\');

        return $path === '' ? $root : $root.DIRECTORY_SEPARATOR.ltrim($path, '/\\');
    }

    /** @param list<string> $paths */
    private function failure(
        mixed $format,
        string $message,
        ?string $module = null,
        array $paths = [],
    ): int {
        return $this->result(
            $format,
            'error',
            $module,
            null,
            $paths,
            '',
            ExitPolicy::TOOL_ERROR,
            $message,
        );
    }

    /** @param list<string> $paths */
    private function result(
        mixed $format,
        string $status,
        ?string $module,
        ?string $runner,
        array $paths,
        string $output,
        int $exitCode,
        ?string $error,
    ): int {
        if ($format === 'json') {
            try {
                $this->line(json_encode([
                    'schema_version' => 1,
                    'status' => $status,
                    'operation' => 'test',
                    'module' => $module,
                    'runner' => $runner,
                    'paths' => $paths,
                    'output' => $output,
                    'exit_code' => $exitCode,
                    'error' => $error,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            } catch (JsonException $exception) {
                $this->components->error('Test output could not be encoded: '.$exception->getMessage());

                return ExitPolicy::TOOL_ERROR;
            }

            return $exitCode;
        }

        foreach ($paths as $path) {
            $this->line($path);
        }

        if ($output !== '') {
            $this->output->write($output);
        }

        if ($error !== null) {
            $this->components->error($error);
        } elseif ($status === 'no_tests') {
            $this->components->warn('No Module tests discovered.');
        } elseif ($status === 'listed') {
            $this->components->info('Module test paths listed successfully.');
        }

        return $exitCode;
    }
}

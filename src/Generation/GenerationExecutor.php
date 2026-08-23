<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use Cluion\Moduark\Architecture\ExitPolicy;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Throwable;

final readonly class GenerationExecutor
{
    public function __construct(private Filesystem $filesystem)
    {
    }

    /** @param callable(GenerationTarget): int $delegate */
    public function execute(GenerationPlan $plan, callable $delegate): GenerationExecutionResult
    {
        try {
            $snapshots = $this->snapshots($plan);
            $createdDirectories = $this->missingDirectories($plan);
        } catch (Throwable $exception) {
            return GenerationExecutionResult::failure(
                ExitPolicy::TOOL_ERROR,
                $exception->getMessage(),
                [],
                false,
            );
        }

        try {
            foreach ($plan->targets() as $target) {
                $template = $target->template();

                if ($template !== null) {
                    $this->filesystem->ensureDirectoryExists(dirname($target->filePath()));
                    $this->filesystem->replace($target->filePath(), $template->render($this->filesystem));
                    $exitCode = 0;
                } else {
                    $exitCode = $delegate($target);
                }

                if ($exitCode !== 0) {
                    return $this->failure(
                        $exitCode,
                        null,
                        $snapshots,
                        $createdDirectories,
                    );
                }

                if (! is_file($target->filePath())) {
                    throw new RuntimeException(
                        "Generator [{$target->generatorId()}] did not create planned target [{$target->moduleRelativePath()}].",
                    );
                }
            }
        } catch (Throwable $exception) {
            return $this->failure(
                ExitPolicy::TOOL_ERROR,
                $exception->getMessage(),
                $snapshots,
                $createdDirectories,
            );
        }

        return GenerationExecutionResult::success();
    }

    /**
     * @return array<string, array{exists: bool, contents: string, mode: int|null, relative: string}>
     */
    private function snapshots(GenerationPlan $plan): array
    {
        $snapshots = [];

        foreach ($plan->targets() as $target) {
            $path = $target->filePath();
            $exists = is_file($path);
            $contents = $exists ? $this->filesystem->get($path) : '';
            $permissions = $exists ? fileperms($path) : false;

            $snapshots[$path] = [
                'exists' => $exists,
                'contents' => $contents,
                'mode' => $permissions === false ? null : $permissions & 0777,
                'relative' => $target->moduleRelativePath(),
            ];
        }

        return $snapshots;
    }

    /** @return list<string> */
    private function missingDirectories(GenerationPlan $plan): array
    {
        $directories = [];

        foreach ($plan->targets() as $target) {
            $directory = dirname($target->filePath());

            while (! is_dir($directory)) {
                $directories[$directory] = $directory;
                $parent = dirname($directory);

                if ($parent === $directory) {
                    break;
                }

                $directory = $parent;
            }
        }

        usort(
            $directories,
            static fn (string $left, string $right): int => strlen($right) <=> strlen($left),
        );

        return $directories;
    }

    /**
     * @param array<string, array{exists: bool, contents: string, mode: int|null, relative: string}> $snapshots
     * @param list<string> $createdDirectories
     */
    private function failure(
        int $exitCode,
        ?string $failure,
        array $snapshots,
        array $createdDirectories,
    ): GenerationExecutionResult {
        $rollbackFailures = [];

        foreach (array_reverse($snapshots, true) as $path => $snapshot) {
            try {
                if ($snapshot['exists']) {
                    $this->filesystem->replace($path, $snapshot['contents'], $snapshot['mode']);
                } elseif (file_exists($path) && ! $this->filesystem->delete($path)) {
                    $rollbackFailures[] = $snapshot['relative'];
                }
            } catch (Throwable) {
                $rollbackFailures[] = $snapshot['relative'];
            }
        }

        foreach ($createdDirectories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $entries = scandir($directory);

            if ($entries !== false && count($entries) === 2 && ! @rmdir($directory)) {
                $rollbackFailures[] = $directory;
            }
        }

        return GenerationExecutionResult::failure(
            $exitCode,
            $failure,
            array_values(array_unique($rollbackFailures)),
        );
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Export;

use Cluion\Moduark\Configuration\ModulesConfig;
use ParseError;
use RuntimeException;
use Throwable;

final readonly class ModuleExportMaterializer
{
    public function __construct(
        private ModuleExportFilesystem $filesystem,
        private ModuleExportRenderer $renderer,
        private ModulesConfig $configuration,
        private string $applicationBasePath,
    ) {
    }

    public function materialize(ModuleExportPlan $plan): ModuleExportExecutionResult
    {
        if (! $plan->ready()) {
            return ModuleExportExecutionResult::failure('A blocked export plan cannot be materialized.');
        }

        $target = $this->absoluteTarget($plan);
        $parent = dirname($target);
        $createdParents = $this->missingParents($parent);
        $staging = null;

        try {
            $staging = $parent.'/.moduark-export-'.strtolower($plan->module()->name()).'-'.bin2hex(random_bytes(8));
            $this->assertSafeTarget($target);
            $this->filesystem->ensureDirectory($parent);
            $this->assertSafeTarget($target);
            $this->filesystem->ensureDirectory($staging);

            foreach ($plan->files() as $file) {
                $contents = $file->operation() === 'generate'
                    ? $this->renderer->render($plan, $file)
                    : $this->copyContents($plan, $file);
                $this->validatePhp($file->destination(), $contents);
                $this->filesystem->write($staging.'/'.$file->destination(), $contents);
            }

            $this->assertSafeTarget($target);
            $this->filesystem->moveDirectory($staging, $target);
        } catch (Throwable $exception) {
            return ModuleExportExecutionResult::failure(
                $exception->getMessage(),
                $this->rollback($staging, $createdParents),
            );
        }

        return ModuleExportExecutionResult::success();
    }

    private function copyContents(ModuleExportPlan $plan, ExportPlanFile $file): string
    {
        $source = $file->source();

        if ($source === null) {
            throw new RuntimeException("Copy target [{$file->destination()}] has no source.");
        }

        $root = rtrim($this->configuration->path(), '/\\').'/'.$plan->module()->name();
        $contents = $this->filesystem->read($root.'/'.$source);
        $prefix = 'namespace:';
        $transform = $file->transform();

        if ($transform === null) {
            return $contents;
        }

        if (! str_starts_with($transform, $prefix) || ! str_contains($transform, '=>')) {
            throw new RuntimeException("Unsupported copy transform [{$transform}].");
        }

        [$from, $to] = explode('=>', substr($transform, strlen($prefix)), 2);

        if ($from === '' || $to === '') {
            throw new RuntimeException("Invalid namespace transform [{$transform}].");
        }

        return str_replace($from, $to, $contents);
    }

    private function validatePhp(string $destination, string $contents): void
    {
        if (! str_ends_with(strtolower($destination), '.php')) {
            return;
        }

        try {
            if (token_get_all($contents, TOKEN_PARSE) === []) {
                throw new RuntimeException("Exported PHP target [{$destination}] is empty.");
            }
        } catch (ParseError $exception) {
            throw new RuntimeException(
                "Exported PHP target [{$destination}] is invalid: {$exception->getMessage()}",
                0,
                $exception,
            );
        }
    }

    private function absoluteTarget(ModuleExportPlan $plan): string
    {
        return rtrim(str_replace('\\', '/', $this->applicationBasePath), '/').'/'.$plan->target();
    }

    private function assertSafeTarget(string $target): void
    {
        $base = rtrim(str_replace('\\', '/', $this->applicationBasePath), '/');
        $cursor = str_replace('\\', '/', $target);

        if (! str_starts_with($cursor, $base.'/')) {
            throw new RuntimeException('The export target escaped the application root.');
        }

        if (file_exists($cursor) || is_link($cursor)) {
            throw new RuntimeException("The export target [{$cursor}] already exists.");
        }

        while ($cursor !== $base && str_starts_with($cursor, $base.'/')) {
            if (is_link($cursor)) {
                throw new RuntimeException("The export target ancestor [{$cursor}] is a symbolic link.");
            }

            $parent = dirname($cursor);

            if ($parent === $cursor) {
                break;
            }

            $cursor = str_replace('\\', '/', $parent);
        }
    }

    /** @return list<string> */
    private function missingParents(string $parent): array
    {
        $missing = [];
        $base = rtrim(str_replace('\\', '/', $this->applicationBasePath), '/');
        $cursor = str_replace('\\', '/', $parent);

        while ($cursor !== $base && str_starts_with($cursor, $base.'/') && ! is_dir($cursor)) {
            $missing[] = $cursor;
            $next = dirname($cursor);

            if ($next === $cursor) {
                break;
            }

            $cursor = str_replace('\\', '/', $next);
        }

        return $missing;
    }

    /**
     * @param list<string> $createdParents
     * @return list<string>
     */
    private function rollback(?string $staging, array $createdParents): array
    {
        $failures = [];

        if ($staging !== null) {
            try {
                $this->filesystem->delete($staging);
            } catch (Throwable) {
                $failures[] = $staging;
            }
        }

        foreach ($createdParents as $parent) {
            try {
                $this->filesystem->removeEmptyDirectory($parent);
            } catch (Throwable) {
                $failures[] = $parent;
            }
        }

        return $failures;
    }
}

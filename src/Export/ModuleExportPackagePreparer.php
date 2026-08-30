<?php

declare(strict_types=1);

namespace Cluion\Moduark\Export;

use Cluion\Moduark\Configuration\ModulesConfig;
use ParseError;
use RuntimeException;

final readonly class ModuleExportPackagePreparer
{
    public function __construct(
        private ModuleExportFilesystem $filesystem,
        private ModuleExportRenderer $renderer,
        private ModulesConfig $configuration,
    ) {
    }

    public function prepare(ModuleExportPlan $plan, string $staging): void
    {
        if (! $plan->ready()) {
            throw new RuntimeException('A blocked export plan cannot be prepared.');
        }

        foreach ($plan->files() as $file) {
            $contents = $file->operation() === 'generate'
                ? $this->renderer->render($plan, $file)
                : $this->copyContents($plan, $file);
            $this->validatePhp($file->destination(), $contents);
            $this->filesystem->write($staging.'/'.$file->destination(), $contents);
        }
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

        $contents = str_replace($from, $to, $contents);

        foreach ($plan->dependencies() as $dependency) {
            if ($dependency->kind() !== 'module' || $dependency->namespace() === null) {
                continue;
            }

            $separator = strrpos($dependency->source(), '=');
            $moduleClass = $separator === false
                ? $dependency->source()
                : substr($dependency->source(), $separator + 1);
            $namespaceSeparator = strrpos($moduleClass, '\\');

            if ($namespaceSeparator === false) {
                throw new RuntimeException(
                    "Export dependency [{$dependency->source()}] has no source namespace.",
                );
            }

            $contents = str_replace(
                substr($moduleClass, 0, $namespaceSeparator),
                $dependency->namespace(),
                $contents,
            );
        }

        return $contents;
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
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Export;

use RuntimeException;
use Throwable;

final class NativeModuleExportFilesystem implements ModuleExportFilesystem
{
    public function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (file_exists($path) || is_link($path) || ! @mkdir($path, 0777, true)) {
            throw new RuntimeException("Unable to create export directory [{$path}].");
        }
    }

    public function read(string $path): string
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read export source [{$path}].");
        }

        return $contents;
    }

    public function write(string $path, string $contents): void
    {
        $this->ensureDirectory(dirname($path));
        $handle = @fopen($path, 'xb');

        if ($handle === false) {
            throw new RuntimeException("Unable to create export file [{$path}].");
        }

        try {
            if (fwrite($handle, $contents) !== strlen($contents)
                || ! fflush($handle)
                || (function_exists('fsync') && ! fsync($handle))) {
                throw new RuntimeException("Unable to write export file [{$path}].");
            }
        } catch (Throwable $exception) {
            throw new RuntimeException("Unable to write export file [{$path}].", 0, $exception);
        } finally {
            fclose($handle);
        }
    }

    public function moveDirectory(string $source, string $destination): void
    {
        if (file_exists($destination) || is_link($destination) || ! @rename($source, $destination)) {
            throw new RuntimeException("Unable to publish export target [{$destination}].");
        }
    }

    public function delete(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            if (! @unlink($path) && (file_exists($path) || is_link($path))) {
                throw new RuntimeException("Unable to remove export path [{$path}].");
            }

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        $entries = scandir($path);

        if ($entries === false) {
            throw new RuntimeException("Unable to inspect export directory [{$path}].");
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $this->delete($path.'/'.$entry);
        }

        if (! @rmdir($path)) {
            throw new RuntimeException("Unable to remove export directory [{$path}].");
        }
    }

    public function removeEmptyDirectory(string $path): void
    {
        if (! file_exists($path) && ! is_link($path)) {
            return;
        }

        if (is_link($path) || ! is_dir($path) || ! @rmdir($path)) {
            throw new RuntimeException("Unable to remove non-empty export parent [{$path}].");
        }
    }
}

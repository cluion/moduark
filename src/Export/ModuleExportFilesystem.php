<?php

declare(strict_types=1);

namespace Cluion\Moduark\Export;

interface ModuleExportFilesystem
{
    public function ensureDirectory(string $path): void;

    public function read(string $path): string;

    public function write(string $path, string $contents): void;

    public function moveDirectory(string $source, string $destination): void;

    public function delete(string $path): void;

    public function removeEmptyDirectory(string $path): void;
}

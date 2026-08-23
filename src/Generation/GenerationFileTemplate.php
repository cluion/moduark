<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

final readonly class GenerationFileTemplate
{
    /** @param array<string, string> $replacements */
    public function __construct(
        private string $path,
        private array $replacements,
    ) {
    }

    public function render(Filesystem $filesystem): string
    {
        $source = $filesystem->get($this->path);
        $source = str_replace(
            array_keys($this->replacements),
            array_values($this->replacements),
            $source,
        );

        if (preg_match('/\{\{\s*[A-Za-z][A-Za-z0-9_]*\s*\}\}/', $source) === 1) {
            throw new RuntimeException("Generation template [{$this->path}] has unresolved placeholders.");
        }

        return $source;
    }
}

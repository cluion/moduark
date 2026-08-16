<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Source;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final readonly class SourceAnalysisCacheStore
{
    public function __construct(private string $path)
    {
        if ($this->path === '') {
            throw new InvalidArgumentException('The source analysis cache path must be a non-empty string.');
        }
    }

    public function path(): string
    {
        return $this->path;
    }

    public function load(): ?SourceAnalysisCache
    {
        if (! is_file($this->path)) {
            return null;
        }

        try {
            $payload = (static fn (string $path): mixed => require $path)($this->path);

            return is_array($payload) ? SourceAnalysisCache::fromArray($payload) : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function write(SourceAnalysisCache $cache): void
    {
        if ($this->load()?->toArray() === $cache->toArray()) {
            return;
        }

        $directory = dirname($this->path);

        if (! is_dir($directory) && ! @mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create source analysis cache directory [{$directory}].");
        }

        $temporary = tempnam($directory, 'moduark-analysis-');

        if ($temporary === false) {
            throw new RuntimeException("Unable to write source analysis cache [{$this->path}].");
        }

        $contents = "<?php\n\ndeclare(strict_types=1);\n\nreturn "
            .var_export($cache->toArray(), true)
            .";\n";

        try {
            if (file_put_contents($temporary, $contents, LOCK_EX) === false
                || ! @chmod($temporary, 0666 & ~umask())) {
                throw new RuntimeException("Unable to write source analysis cache [{$this->path}].");
            }

            if (! @rename($temporary, $this->path)) {
                if (is_file($this->path) && ! @unlink($this->path)) {
                    throw new RuntimeException("Unable to write source analysis cache [{$this->path}].");
                }

                if (! @rename($temporary, $this->path)) {
                    throw new RuntimeException("Unable to write source analysis cache [{$this->path}].");
                }
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }

        $this->invalidateOpcache();
    }

    public function clear(): bool
    {
        if (! is_file($this->path)) {
            return false;
        }

        if (! @unlink($this->path)) {
            throw new RuntimeException("Unable to clear source analysis cache [{$this->path}].");
        }

        $this->invalidateOpcache();

        return true;
    }

    private function invalidateOpcache(): void
    {
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->path, true);
        }
    }
}

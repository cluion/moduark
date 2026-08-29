<?php

declare(strict_types=1);

namespace Cluion\Moduark\Cache;

use Cluion\Moduark\Exceptions\ModuleCacheFailed;
use InvalidArgumentException;
use Throwable;

final readonly class ModuleCacheStore
{
    public function __construct(private string $path)
    {
        if ($this->path === '') {
            throw new InvalidArgumentException('The Module cache path must be a non-empty string.');
        }
    }

    public function path(): string
    {
        return $this->path;
    }

    public function load(
        string $expectedModulesPath,
        string $expectedActivationFingerprint,
        string $expectedPackageFingerprint,
    ): ?ModuleCacheManifest
    {
        if (! is_file($this->path)) {
            return null;
        }

        try {
            $payload = (static fn (string $path): mixed => require $path)($this->path);
        } catch (Throwable $exception) {
            throw ModuleCacheFailed::invalid($this->path, $exception);
        }

        if (! is_array($payload)) {
            throw ModuleCacheFailed::invalid($this->path);
        }

        if (($payload['schema_version'] ?? null) !== ModuleCacheManifest::SCHEMA_VERSION) {
            return null;
        }

        try {
            $manifest = ModuleCacheManifest::fromArray($payload);
        } catch (Throwable $exception) {
            throw ModuleCacheFailed::invalid($this->path, $exception);
        }

        return $manifest->modulesPath() === $expectedModulesPath
            && $manifest->activationFingerprint() === $expectedActivationFingerprint
            && $manifest->packageFingerprint() === $expectedPackageFingerprint
                ? $manifest
                : null;
    }

    public function write(ModuleCacheManifest $manifest): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory) && ! @mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw ModuleCacheFailed::directory($directory);
        }

        $temporary = tempnam($directory, 'moduark-');

        if ($temporary === false) {
            throw ModuleCacheFailed::write($this->path);
        }

        $contents = "<?php\n\ndeclare(strict_types=1);\n\nreturn "
            .var_export($manifest->toArray(), true)
            .";\n";

        try {
            if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
                throw ModuleCacheFailed::write($this->path);
            }

            if (! @chmod($temporary, 0666 & ~umask())) {
                throw ModuleCacheFailed::write($this->path);
            }

            if (! @rename($temporary, $this->path)) {
                if (is_file($this->path) && ! @unlink($this->path)) {
                    throw ModuleCacheFailed::write($this->path);
                }

                if (! @rename($temporary, $this->path)) {
                    throw ModuleCacheFailed::write($this->path);
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
            throw ModuleCacheFailed::clear($this->path);
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

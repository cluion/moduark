<?php

declare(strict_types=1);

namespace Cluion\Moduark\Exceptions;

use RuntimeException;
use Throwable;

final class ModuleActivationMutationFailed extends RuntimeException
{
    public static function unsupported(string $driver): self
    {
        return new self("The Module activation driver [{$driver}] does not support atomic mutation.");
    }

    public static function invalidState(string $path, ?Throwable $previous = null): self
    {
        return new self("The Module activation state [{$path}] is invalid.", 0, $previous);
    }

    public static function directory(string $path): self
    {
        return new self("Unable to create Module activation directory [{$path}].");
    }

    public static function lock(string $path): self
    {
        return new self("Unable to lock Module activation state [{$path}].");
    }

    public static function concurrentChange(string $path): self
    {
        return new self("Module activation state [{$path}] changed after planning; retry the command.");
    }

    public static function write(string $path, ?Throwable $previous = null): self
    {
        return new self("Unable to atomically write Module activation state [{$path}].", 0, $previous);
    }

    public static function invalidPlan(): self
    {
        return new self('A blocked Module activation plan cannot be applied.');
    }
}

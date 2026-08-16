<?php

declare(strict_types=1);

namespace Cluion\Moduark\Exceptions;

use RuntimeException;
use Throwable;

final class ModuleCacheFailed extends RuntimeException
{
    public static function invalid(string $path, ?Throwable $previous = null): self
    {
        return new self("Module cache [{$path}] is invalid.", 0, $previous);
    }

    public static function directory(string $path): self
    {
        return new self("Unable to create Module cache directory [{$path}].");
    }

    public static function write(string $path): self
    {
        return new self("Unable to write Module cache [{$path}].");
    }

    public static function clear(string $path): self
    {
        return new self("Unable to clear Module cache [{$path}].");
    }
}

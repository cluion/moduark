<?php

declare(strict_types=1);

namespace Cluion\Moduark\Exceptions;

use RuntimeException;

final class SourceAnalysisFailed extends RuntimeException
{
    public static function scanFailed(string $path, string $message): self
    {
        return new self("Unable to scan Module source path [{$path}]: {$message}");
    }

    public static function unreadableFile(string $path): self
    {
        return new self("Unable to read Module source file [{$path}].");
    }

    public static function invalidSyntax(string $path, int $line, string $message): self
    {
        $location = $line > 0 ? "{$path}:{$line}" : $path;

        return new self("Unable to parse Module source [{$location}]: {$message}");
    }

    public static function duplicateSymbol(
        string $symbol,
        string $firstFile,
        string $secondFile,
    ): self {
        return new self(
            "Module symbol [{$symbol}] is declared by both [{$firstFile}] and [{$secondFile}].",
        );
    }

    public static function invalidReference(string $symbol, string $file, int $line): self
    {
        return new self(
            "Module source reference [{$symbol}] at [{$file}:{$line}] has no matching symbol owner.",
        );
    }
}

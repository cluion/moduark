<?php

declare(strict_types=1);

namespace Cluion\Moduark\Exceptions;

use RuntimeException;

final class SourceAnalysisFailed extends RuntimeException
{
    private const DIAGNOSTIC_CODE = 'MOD-ANALYSIS-001';

    private function __construct(
        string $message,
        private readonly ?string $location,
        private readonly string $suggestion,
    ) {
        parent::__construct($message);
    }

    public static function scanFailed(string $path, string $message): self
    {
        return new self(
            "Unable to scan Module source path [{$path}]: {$message}",
            $path,
            'Check that the Module source directory exists and is readable, then rerun module:check.',
        );
    }

    public static function unreadableFile(string $path): self
    {
        return new self(
            "Unable to read Module source file [{$path}].",
            $path,
            'Make the Module source file readable, then rerun module:check.',
        );
    }

    public static function invalidSyntax(string $path, int $line, string $message): self
    {
        $location = $line > 0 ? "{$path}:{$line}" : $path;

        return new self(
            "Unable to parse Module source [{$location}]: {$message}",
            $location,
            'Fix the PHP syntax at the reported location, then rerun module:check.',
        );
    }

    public static function duplicateSymbol(
        string $symbol,
        string $firstFile,
        string $secondFile,
    ): self {
        return new self(
            "Module symbol [{$symbol}] is declared by both [{$firstFile}] and [{$secondFile}].",
            $firstFile,
            'Keep one canonical declaration for the symbol, then rerun module:check.',
        );
    }

    public static function invalidReference(string $symbol, string $file, int $line): self
    {
        return new self(
            "Module source reference [{$symbol}] at [{$file}:{$line}] has no matching symbol owner.",
            "{$file}:{$line}",
            'Ensure the referenced symbol is declared once under a discovered Module, then rerun module:check.',
        );
    }

    public function diagnosticCode(): string
    {
        return self::DIAGNOSTIC_CODE;
    }

    public function location(): ?string
    {
        return $this->location;
    }

    public function suggestion(): string
    {
        return $this->suggestion;
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Export;

use InvalidArgumentException;

final readonly class ExportTargetMapping
{
    private function __construct(
        private string $module,
        private string $target,
    ) {
    }

    public static function fromString(string $mapping): self
    {
        if (trim($mapping) !== $mapping || substr_count($mapping, '=') !== 1) {
            throw self::invalid($mapping);
        }

        [$module, $target] = explode('=', $mapping, 2);

        if ($module === ''
            || preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $module) !== 1
            || $target === '') {
            throw self::invalid($mapping);
        }

        return new self($module, $target);
    }

    public function module(): string
    {
        return $this->module;
    }

    public function target(): string
    {
        return $this->target;
    }

    private static function invalid(string $mapping): InvalidArgumentException
    {
        return new InvalidArgumentException(
            "Invalid export target mapping [{$mapping}]; expected Module=portable/path.",
        );
    }
}

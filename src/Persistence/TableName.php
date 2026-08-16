<?php

declare(strict_types=1);

namespace Cluion\Moduark\Persistence;

final class TableName
{
    private const PATTERN = '/\A[A-Za-z_$][A-Za-z0-9_$-]*(?:\.[A-Za-z_$][A-Za-z0-9_$-]*)*\z/D';

    private function __construct()
    {
    }

    public static function valid(string $name): bool
    {
        return preg_match(self::PATTERN, $name) === 1;
    }

    public static function key(string $name): string
    {
        return strtolower($name);
    }
}

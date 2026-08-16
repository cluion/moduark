<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Baseline;

final class PortablePath
{
    public static function relative(string $path, string $basePath): string
    {
        $path = str_replace('\\', '/', $path);
        $basePath = rtrim(str_replace('\\', '/', $basePath), '/');
        $prefix = $basePath.'/';
        $windowsPath = preg_match('/\A[A-Za-z]:\//', $path) === 1;
        $insideBasePath = $windowsPath
            ? strncasecmp($path, $prefix, strlen($prefix)) === 0
            : strncmp($path, $prefix, strlen($prefix)) === 0;

        return $insideBasePath ? substr($path, strlen($prefix)) : $path;
    }
}

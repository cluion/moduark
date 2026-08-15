<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Boundary;

use Cluion\Moduark\Analysis\Source\SourceSymbol;
use Cluion\Moduark\Discovery\DiscoveredModule;

final class ConventionPublicApi implements PublicApi
{
    /** @var list<string> */
    private const PUBLIC_DIRECTORIES = [
        'Contracts',
        'Data',
        'Events',
    ];

    public function includes(SourceSymbol $symbol, DiscoveredModule $owner): bool
    {
        if ($symbol->name() === $owner->moduleClass()) {
            return true;
        }

        $modulePath = $this->normalize(dirname($owner->path()));
        $symbolPath = $this->normalize($symbol->file());
        $prefix = rtrim($modulePath, '/').'/';

        if (! str_starts_with($symbolPath, $prefix)) {
            return false;
        }

        $relativePath = substr($symbolPath, strlen($prefix));
        $separator = strpos($relativePath, '/');
        $directory = $separator === false
            ? ''
            : substr($relativePath, 0, $separator);

        return in_array($directory, self::PUBLIC_DIRECTORIES, true);
    }

    private function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}

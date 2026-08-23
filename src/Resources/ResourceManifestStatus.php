<?php

declare(strict_types=1);

namespace Cluion\Moduark\Resources;

final readonly class ResourceManifestStatus
{
    public function __construct(private bool $cached)
    {
    }

    public function cached(): bool
    {
        return $this->cached;
    }
}

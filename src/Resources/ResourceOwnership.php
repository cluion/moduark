<?php

declare(strict_types=1);

namespace Cluion\Moduark\Resources;

final readonly class ResourceOwnership
{
    public function __construct(private bool $conventionsManagedExternally)
    {
    }

    public function conventionsManagedExternally(): bool
    {
        return $this->conventionsManagedExternally;
    }
}

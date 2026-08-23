<?php

declare(strict_types=1);

namespace Cluion\Moduark\Resources;

use Cluion\Moduark\Exceptions\ResourceManifestFailed;

final readonly class ResourcePlugin
{
    public function __construct(
        private string $id,
        private ResourceDiscoverer $discoverer,
        private ResourceHandler $handler,
    ) {
        if (preg_match('/\A[a-z][a-z0-9.-]*\z/', $this->id) !== 1) {
            throw ResourceManifestFailed::invalidIdentity('plugin', $this->id);
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function discoverer(): ResourceDiscoverer
    {
        return $this->discoverer;
    }

    public function handler(): ResourceHandler
    {
        return $this->handler;
    }
}

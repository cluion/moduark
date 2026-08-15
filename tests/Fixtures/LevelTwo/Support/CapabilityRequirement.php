<?php

declare(strict_types=1);

namespace Tests\Fixtures\LevelTwo\Support;

use InvalidArgumentException;

final readonly class CapabilityRequirement
{
    /**
     * @param class-string<Capability> $capability
     * @param class-string $port
     * @param class-string $adapter
     */
    public function __construct(
        private string $capability,
        private string $port,
        private string $adapter,
    ) {
        if (! interface_exists($port)) {
            throw new InvalidArgumentException("Capability Port [{$port}] must be an interface.");
        }

        if (! class_exists($adapter) || ! is_a($adapter, $port, true)) {
            throw new InvalidArgumentException(
                "Capability Adapter [{$adapter}] must implement consumer Port [{$port}].",
            );
        }
    }

    /**
     * @return class-string<Capability>
     */
    public function capability(): string
    {
        return $this->capability;
    }

    /**
     * @return class-string
     */
    public function port(): string
    {
        return $this->port;
    }

    /**
     * @return class-string
     */
    public function adapter(): string
    {
        return $this->adapter;
    }
}

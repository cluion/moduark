<?php

declare(strict_types=1);

namespace Tests\Fixtures\LevelTwo\Support;

use Cluion\Moduark\Module;

final readonly class CapabilityBinding
{
    /**
     * @param class-string<Capability> $capability
     * @param class-string<Module> $provider
     * @param class-string<Module> $consumer
     * @param class-string $port
     * @param class-string $adapter
     */
    public function __construct(
        private string $capability,
        private string $provider,
        private string $consumer,
        private string $port,
        private string $adapter,
    ) {
    }

    /** @return class-string<Capability> */
    public function capability(): string
    {
        return $this->capability;
    }

    /** @return class-string<Module> */
    public function provider(): string
    {
        return $this->provider;
    }

    /** @return class-string<Module> */
    public function consumer(): string
    {
        return $this->consumer;
    }

    /** @return class-string */
    public function port(): string
    {
        return $this->port;
    }

    /** @return class-string */
    public function adapter(): string
    {
        return $this->adapter;
    }

    /**
     * @return array{
     *     capability: class-string<Capability>,
     *     provider: class-string<Module>,
     *     consumer: class-string<Module>,
     *     port: class-string,
     *     adapter: class-string
     * }
     */
    public function toArray(): array
    {
        return [
            'capability' => $this->capability,
            'provider' => $this->provider,
            'consumer' => $this->consumer,
            'port' => $this->port,
            'adapter' => $this->adapter,
        ];
    }
}

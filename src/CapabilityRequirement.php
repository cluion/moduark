<?php

declare(strict_types=1);

namespace Cluion\Moduark;

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
    }

    /**
     * @param array{
     *     capability: class-string<Capability>,
     *     port: class-string,
     *     adapter: class-string
     * } $values
     */
    public static function fromArray(array $values): self
    {
        return new self(
            $values['capability'],
            $values['port'],
            $values['adapter'],
        );
    }

    /** @return class-string<Capability> */
    public function capability(): string
    {
        return $this->capability;
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
     *     port: class-string,
     *     adapter: class-string
     * }
     */
    public function toArray(): array
    {
        return [
            'capability' => $this->capability,
            'port' => $this->port,
            'adapter' => $this->adapter,
        ];
    }
}

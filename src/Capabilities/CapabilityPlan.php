<?php

declare(strict_types=1);

namespace Cluion\Moduark\Capabilities;

use Cluion\Moduark\Capability;
use Cluion\Moduark\Module;

final readonly class CapabilityPlan
{
    /**
     * @param list<CapabilityBinding> $bindings
     */
    public function __construct(private array $bindings)
    {
    }

    /**
     * @param list<array{
     *     capability: class-string<Capability>,
     *     provider: class-string<Module>,
     *     consumer: class-string<Module>,
     *     port: class-string,
     *     adapter: class-string
     * }> $values
     */
    public static function fromArray(array $values): self
    {
        return new self(array_map(
            static fn (array $binding): CapabilityBinding => CapabilityBinding::fromArray($binding),
            $values,
        ));
    }

    /**
     * @return list<CapabilityBinding>
     */
    public function bindings(): array
    {
        return $this->bindings;
    }

    /**
     * @return list<array{
     *     capability: class-string<Capability>,
     *     provider: class-string<Module>,
     *     consumer: class-string<Module>,
     *     port: class-string,
     *     adapter: class-string
     * }>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (CapabilityBinding $binding): array => $binding->toArray(),
            $this->bindings,
        );
    }
}

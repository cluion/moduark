<?php

declare(strict_types=1);

namespace Tests\Fixtures\LevelTwo\Support;

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

<?php

declare(strict_types=1);

namespace Tests\Fixtures\LevelTwo\Support;

interface CapabilityMetadata
{
    /**
     * @return list<class-string<Capability>>
     */
    public function provides(): array;

    /**
     * @return list<CapabilityRequirement>
     */
    public function requires(): array;
}

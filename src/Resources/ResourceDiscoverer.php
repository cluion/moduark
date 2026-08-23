<?php

declare(strict_types=1);

namespace Cluion\Moduark\Resources;

use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Metadata\ModuleDescriptor;

interface ResourceDiscoverer
{
    /**
     * @param array<string, mixed> $moduleConfiguration
     * @return list<ResourceDescriptor>
     */
    public function discover(
        DiscoveredModule $module,
        ModuleDescriptor $metadata,
        array $moduleConfiguration,
    ): array;
}

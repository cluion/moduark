<?php

declare(strict_types=1);

namespace Cluion\Moduark\Lifecycle;

use Cluion\Moduark\Metadata\ModuleDescriptor;

final readonly class OrderedModules
{
    /**
     * @param list<ModuleDescriptor> $descriptors
     */
    public function __construct(private array $descriptors)
    {
    }

    /**
     * @return list<ModuleDescriptor>
     */
    public function all(): array
    {
        return $this->descriptors;
    }
}

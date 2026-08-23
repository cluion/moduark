<?php

declare(strict_types=1);

namespace Cluion\Moduark\Resources;

final class ResourceRegistrationState
{
    /** @var array<string, true> */
    private array $handled = [];

    public function claim(ResourcePhase $phase, ResourceDescriptor $resource): bool
    {
        $key = $phase->value.'|'.strtolower(
            $resource->moduleClass().'|'.$resource->plugin().'|'.$resource->identity(),
        );

        if (isset($this->handled[$key])) {
            return false;
        }

        $this->handled[$key] = true;

        return true;
    }
}

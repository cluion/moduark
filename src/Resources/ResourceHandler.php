<?php

declare(strict_types=1);

namespace Cluion\Moduark\Resources;

interface ResourceHandler
{
    public function phase(): ResourcePhase;

    public function handle(ResourceDescriptor $resource, ResourceRuntime $runtime): void;
}

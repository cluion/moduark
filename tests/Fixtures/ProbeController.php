<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class ProbeController
{
    public function __invoke(): string
    {
        return 'moduark-probe';
    }
}

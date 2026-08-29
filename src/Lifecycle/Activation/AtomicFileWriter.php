<?php

declare(strict_types=1);

namespace Cluion\Moduark\Lifecycle\Activation;

interface AtomicFileWriter
{
    public function write(string $path, string $contents): void;
}

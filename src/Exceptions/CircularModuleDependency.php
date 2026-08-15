<?php

declare(strict_types=1);

namespace Cluion\Moduark\Exceptions;

use Cluion\Moduark\Module;
use RuntimeException;

final class CircularModuleDependency extends RuntimeException
{
    /**
     * @param non-empty-list<class-string<Module>> $cycle
     */
    public function __construct(private readonly array $cycle)
    {
        parent::__construct('Circular module dependency: '.implode(' -> ', $cycle));
    }

    /**
     * @return non-empty-list<class-string<Module>>
     */
    public function cycle(): array
    {
        return $this->cycle;
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Graph;

use Cluion\Moduark\Module;
use InvalidArgumentException;

final readonly class ModuleGraphEdge
{
    /** @var class-string<Module> */
    private string $source;

    /** @var class-string<Module> */
    private string $target;

    /**
     * @param string $source
     * @param string $target
     */
    public function __construct(
        string $source,
        string $target,
        private string $evidence,
    ) {
        if (! is_a($source, Module::class, true) || ! is_a($target, Module::class, true)) {
            throw new InvalidArgumentException('Module graph edge endpoints must extend Module.');
        }

        if (trim($evidence) === '') {
            throw new InvalidArgumentException('A Module graph edge must preserve its evidence.');
        }

        $this->source = $source;
        $this->target = $target;
    }

    /**
     * @return class-string<Module>
     */
    public function source(): string
    {
        return $this->source;
    }

    /**
     * @return class-string<Module>
     */
    public function target(): string
    {
        return $this->target;
    }

    public function evidence(): string
    {
        return $this->evidence;
    }

    /**
     * @return array{source: class-string<Module>, target: class-string<Module>, evidence: string}
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'target' => $this->target,
            'evidence' => $this->evidence,
        ];
    }
}

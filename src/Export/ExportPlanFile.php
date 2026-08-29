<?php

declare(strict_types=1);

namespace Cluion\Moduark\Export;

final readonly class ExportPlanFile
{
    public function __construct(
        private string $operation,
        private ?string $source,
        private string $destination,
        private ?string $transform = null,
    ) {
    }

    public function operation(): string
    {
        return $this->operation;
    }

    public function source(): ?string
    {
        return $this->source;
    }

    public function destination(): string
    {
        return $this->destination;
    }

    public function transform(): ?string
    {
        return $this->transform;
    }

    /**
     * @return array{
     *     operation: string,
     *     source: ?string,
     *     destination: string,
     *     transform: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'operation' => $this->operation,
            'source' => $this->source,
            'destination' => $this->destination,
            'transform' => $this->transform,
        ];
    }
}

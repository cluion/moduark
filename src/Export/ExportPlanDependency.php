<?php

declare(strict_types=1);

namespace Cluion\Moduark\Export;

final readonly class ExportPlanDependency
{
    public const RESOLVED = 'resolved';

    public const MANUAL = 'manual';

    public function __construct(
        private string $kind,
        private string $source,
        private ?string $package,
        private ?string $constraint,
        private string $status,
        private ?string $namespace = null,
    ) {
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function package(): ?string
    {
        return $this->package;
    }

    public function constraint(): ?string
    {
        return $this->constraint;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function namespace(): ?string
    {
        return $this->namespace;
    }

    /**
     * @return array{
     *     kind: string,
     *     source: string,
     *     package: ?string,
     *     constraint: ?string,
     *     status: string,
     *     namespace: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'source' => $this->source,
            'package' => $this->package,
            'constraint' => $this->constraint,
            'status' => $this->status,
            'namespace' => $this->namespace,
        ];
    }
}

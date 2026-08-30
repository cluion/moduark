<?php

declare(strict_types=1);

namespace Cluion\Moduark\Export;

use LogicException;

final readonly class PackageExportPlan
{
    public function __construct(
        private ModuleExportPlan $plan,
        private string $constraint,
    ) {
    }

    public function plan(): ModuleExportPlan
    {
        return $this->plan;
    }

    public function constraint(): string
    {
        return $this->constraint;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = $this->plan->toArray();
        $package = $payload['package'];

        if (! is_array($package)) {
            throw new LogicException('A package export plan must contain package metadata.');
        }

        $payload['package'] = [
            ...$package,
            'constraint' => $this->constraint,
        ];

        return $payload;
    }
}

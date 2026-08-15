<?php

declare(strict_types=1);

namespace Cluion\Moduark\Architecture;

final readonly class EffectiveRule
{
    public function __construct(
        private RuleId $id,
        private bool $enabled,
        private Severity $severity,
    ) {
    }

    public function id(): RuleId
    {
        return $this->id;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function severity(): Severity
    {
        return $this->severity;
    }

    /**
     * @return array{enabled: bool, severity: string}
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'severity' => $this->severity->value,
        ];
    }
}

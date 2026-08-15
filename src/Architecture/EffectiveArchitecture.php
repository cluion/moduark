<?php

declare(strict_types=1);

namespace Cluion\Moduark\Architecture;

final readonly class EffectiveArchitecture
{
    public function __construct(
        private Level $configuredLevel,
        private Level $level,
        private EffectiveRules $rules,
    ) {
    }

    public function configuredLevel(): Level
    {
        return $this->configuredLevel;
    }

    public function level(): Level
    {
        return $this->level;
    }

    public function levelOverridden(): bool
    {
        return $this->configuredLevel !== $this->level;
    }

    public function rules(): EffectiveRules
    {
        return $this->rules;
    }

    /**
     * @return array{
     *     configured_level: int,
     *     level: int,
     *     level_overridden: bool,
     *     rules: array<string, array{enabled: bool, severity: string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'configured_level' => $this->configuredLevel->value,
            'level' => $this->level->value,
            'level_overridden' => $this->levelOverridden(),
            'rules' => $this->rules->toArray(),
        ];
    }
}

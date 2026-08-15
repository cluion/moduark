<?php

declare(strict_types=1);

namespace Cluion\Moduark\Architecture;

use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Exceptions\InvalidArchitectureConfiguration;

final readonly class RuleResolver
{
    public function __construct(private RulePresets $presets)
    {
    }

    public function resolve(ModulesConfig $configuration, ?Level $level = null): EffectiveArchitecture
    {
        $configuredLevel = $configuration->level();
        $selectedLevel = $level ?? $configuredLevel;
        $overrides = $this->overrides($configuration->rules());
        $rules = [];

        foreach (RuleId::cases() as $rule) {
            $presetSeverity = $this->presets->severity($selectedLevel, $rule);
            $enabled = $presetSeverity !== null;

            if (array_key_exists($rule->value, $overrides)) {
                $enabled = $overrides[$rule->value];
            }

            $rules[] = new EffectiveRule(
                $rule,
                $enabled,
                $presetSeverity ?? $rule->defaultSeverity(),
            );
        }

        return new EffectiveArchitecture(
            $configuredLevel,
            $selectedLevel,
            new EffectiveRules($rules),
        );
    }

    /**
     * @param array<mixed> $configured
     * @return array<string, bool>
     */
    private function overrides(array $configured): array
    {
        $overrides = [];

        foreach ($configured as $rule => $value) {
            if (! is_string($rule)) {
                throw InvalidArchitectureConfiguration::stringRuleKeys();
            }

            if (RuleId::tryFrom($rule) === null) {
                throw InvalidArchitectureConfiguration::unknownRule($rule);
            }

            if (! is_bool($value)) {
                throw InvalidArchitectureConfiguration::booleanOverride($rule, $value);
            }

            $overrides[$rule] = $value;
        }

        return $overrides;
    }
}

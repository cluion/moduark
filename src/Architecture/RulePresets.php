<?php

declare(strict_types=1);

namespace Cluion\Moduark\Architecture;

final class RulePresets
{
    /**
     * A null entry means that the preset leaves the rule disabled.
     *
     * @var array<string, array{0: ?string, 1: ?string, 2: ?string, 3: ?string}>
     */
    private const MATRIX = [
        'valid_module_structure' => ['error', 'error', 'error', 'error'],
        'unique_module_identity' => ['error', 'error', 'error', 'error'],
        'missing_dependencies' => [null, 'error', 'error', 'error'],
        'undeclared_dependencies' => [null, 'error', 'error', 'error'],
        'cycles' => [null, 'error', 'error', 'error'],
        'internal_api_access' => [null, 'error', 'error', 'error'],
        'capability_contracts' => [null, null, 'error', 'error'],
        'adapter_boundaries' => [null, null, 'error', 'error'],
        'cross_module_model_access' => [null, null, null, 'error'],
        'database_ownership' => [null, null, null, 'error'],
        'migration_ownership' => [null, null, null, 'error'],
        'cross_module_foreign_keys' => [null, null, null, 'warning'],
        'cross_module_transactions' => [null, null, null, 'warning'],
        'explicit_public_exports' => [null, null, null, 'error'],
    ];

    public function severity(Level $level, RuleId $rule): ?Severity
    {
        $severity = self::MATRIX[$rule->value][$level->value];

        return $severity === null ? null : Severity::from($severity);
    }
}

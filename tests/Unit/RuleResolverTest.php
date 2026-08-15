<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Architecture\Level;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RulePresets;
use Cluion\Moduark\Architecture\RuleResolver;
use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Exceptions\InvalidArchitectureConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RuleResolverTest extends TestCase
{
    /**
     * @param array<string, string> $expected
     */
    #[DataProvider('presetMatrix')]
    public function test_it_resolves_every_level_preset(int $level, array $expected): void
    {
        $effective = $this->resolver()->resolve($this->configuration($level));
        $enabled = [];

        foreach ($effective->rules()->enabled() as $rule) {
            $enabled[$rule->id()->value] = $rule->severity()->value;
        }

        self::assertSame($expected, $enabled);
        self::assertCount(count(RuleId::cases()), $effective->rules()->all());
        self::assertSame(Level::from($level), $effective->configuredLevel());
        self::assertSame(Level::from($level), $effective->level());
        self::assertFalse($effective->levelOverridden());
    }

    /**
     * @return iterable<string, array{int, array<string, string>}>
     */
    public static function presetMatrix(): iterable
    {
        yield 'Level 0 Organization' => [
            0,
            [
                'valid_module_structure' => 'error',
                'unique_module_identity' => 'error',
            ],
        ];

        yield 'Level 1 Modular' => [
            1,
            [
                'valid_module_structure' => 'error',
                'unique_module_identity' => 'error',
                'missing_dependencies' => 'error',
                'undeclared_dependencies' => 'error',
                'cycles' => 'error',
                'internal_api_access' => 'error',
            ],
        ];

        yield 'Level 2 Decoupled' => [
            2,
            [
                'valid_module_structure' => 'error',
                'unique_module_identity' => 'error',
                'missing_dependencies' => 'error',
                'undeclared_dependencies' => 'error',
                'cycles' => 'error',
                'internal_api_access' => 'error',
                'capability_contracts' => 'error',
                'adapter_boundaries' => 'error',
            ],
        ];

        yield 'Level 3 Isolated' => [
            3,
            [
                'valid_module_structure' => 'error',
                'unique_module_identity' => 'error',
                'missing_dependencies' => 'error',
                'undeclared_dependencies' => 'error',
                'cycles' => 'error',
                'internal_api_access' => 'error',
                'capability_contracts' => 'error',
                'adapter_boundaries' => 'error',
                'cross_module_model_access' => 'error',
                'database_ownership' => 'error',
                'migration_ownership' => 'error',
                'cross_module_foreign_keys' => 'warning',
                'cross_module_transactions' => 'warning',
                'explicit_public_exports' => 'error',
            ],
        ];
    }

    public function test_cli_level_replaces_the_preset_before_configured_overrides(): void
    {
        $effective = $this->resolver()->resolve(
            $this->configuration(3, [
                'valid_module_structure' => false,
                'cycles' => true,
            ]),
            Level::Organization,
        );

        self::assertSame(Level::Isolated, $effective->configuredLevel());
        self::assertSame(Level::Organization, $effective->level());
        self::assertTrue($effective->levelOverridden());
        self::assertFalse($effective->rules()->get(RuleId::ValidModuleStructure)->enabled());
        self::assertTrue($effective->rules()->get(RuleId::Cycles)->enabled());
        self::assertFalse($effective->rules()->get(RuleId::DatabaseOwnership)->enabled());
    }

    public function test_boolean_override_uses_the_rule_default_severity(): void
    {
        $effective = $this->resolver()->resolve($this->configuration(0, [
            'cross_module_transactions' => true,
        ]));

        $rule = $effective->rules()->get(RuleId::CrossModuleTransactions);

        self::assertTrue($rule->enabled());
        self::assertSame('warning', $rule->severity()->value);
    }

    /**
     * @param array<mixed> $rules
     */
    #[DataProvider('invalidOverrides')]
    public function test_invalid_overrides_are_rejected(array $rules, string $message): void
    {
        $this->expectException(InvalidArchitectureConfiguration::class);
        $this->expectExceptionMessage($message);

        $this->resolver()->resolve($this->configuration(1, $rules));
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function invalidOverrides(): iterable
    {
        yield 'unknown rule' => [
            ['cycle' => true],
            'contains unknown rule [cycle]',
        ];

        yield 'structured override is reserved' => [
            ['cycles' => ['enabled' => true]],
            'rules.cycles configuration must be a boolean; received array',
        ];

        yield 'integer rule key' => [
            [0 => true],
            'must use rule IDs as string keys',
        ];

        yield 'integer override' => [
            ['cycles' => 1],
            'rules.cycles configuration must be a boolean; received int',
        ];
    }

    public function test_effective_configuration_export_is_deterministic_and_scalar(): void
    {
        $resolver = $this->resolver();
        $configuration = $this->configuration(2, ['cycles' => false]);
        $first = $resolver->resolve($configuration)->toArray();
        $second = $resolver->resolve($configuration)->toArray();

        self::assertSame($first, $second);
        self::assertSame(2, $first['configured_level']);
        self::assertFalse($first['rules']['cycles']['enabled']);

        array_walk_recursive($first, static function (mixed $value): void {
            self::assertTrue(is_scalar($value) || $value === null);
        });
    }

    private function resolver(): RuleResolver
    {
        return new RuleResolver(new RulePresets);
    }

    /**
     * @param array<mixed> $rules
     */
    private function configuration(int $level, array $rules = []): ModulesConfig
    {
        return ModulesConfig::from(
            [
                'path' => '/app/Modules',
                'architecture' => [
                    'level' => 1,
                    'rules' => [],
                ],
            ],
            [
                'architecture' => [
                    'level' => $level,
                    'rules' => $rules,
                ],
            ],
        );
    }
}

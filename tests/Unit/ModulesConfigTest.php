<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Architecture\Level;
use Cluion\Moduark\Configuration\ModulesConfig;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ModulesConfigTest extends TestCase
{
    public function test_partial_nested_overrides_keep_defaults(): void
    {
        $configuration = ModulesConfig::from(
            [
                'path' => '/app/Modules',
                'architecture' => [
                    'level' => 1,
                    'baseline' => '/app/moduark-baseline.json',
                    'suppressions' => '/app/moduark-suppressions.json',
                    'rules' => [
                        'cycles' => true,
                        'internal_api_access' => true,
                    ],
                ],
            ],
            [
                'architecture' => [
                    'rules' => [
                        'cycles' => false,
                    ],
                ],
            ],
        );

        self::assertSame(Level::Modular, $configuration->level());
        self::assertSame('/app/moduark-baseline.json', $configuration->baselinePath());
        self::assertSame('/app/moduark-suppressions.json', $configuration->suppressionPath());
        self::assertSame([
            'cycles' => false,
            'internal_api_access' => true,
        ], $configuration->rules());
    }

    public function test_explicit_level_and_path_override_defaults(): void
    {
        $configuration = ModulesConfig::from(
            [
                'path' => '/app/Modules',
                'architecture' => ['level' => 1, 'rules' => []],
            ],
            [
                'path' => '/domain/Modules',
                'architecture' => ['level' => 0],
            ],
        );

        self::assertSame('/domain/Modules', $configuration->path());
        self::assertSame(Level::Organization, $configuration->level());
    }

    public function test_activation_path_is_configurable_and_legacy_programmatic_config_has_a_fallback(): void
    {
        $configured = ModulesConfig::from(
            [
                'path' => '/app/Modules',
                'activation' => ['path' => '/state/moduark-modules.json'],
                'architecture' => ['level' => 1, 'rules' => []],
            ],
            [],
        );
        $legacy = ModulesConfig::from(
            [
                'path' => '/app/Modules',
                'architecture' => ['level' => 1, 'rules' => []],
            ],
            [],
        );

        self::assertSame('/state/moduark-modules.json', $configured->activationPath());
        self::assertSame('/app/moduark-modules.json', $legacy->activationPath());
        self::assertFalse($legacy->nativeGeneratorBridgeEnabled());
    }

    public function test_native_generator_bridge_is_explicitly_opt_in(): void
    {
        $configuration = ModulesConfig::from(
            [
                'path' => '/app/Modules',
                'generation' => ['native_bridge' => false],
                'architecture' => ['level' => 1, 'rules' => []],
            ],
            ['generation' => ['native_bridge' => true]],
        );

        self::assertTrue($configuration->nativeGeneratorBridgeEnabled());
    }

    public function test_native_generator_bridge_rejects_non_boolean_configuration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('native_bridge configuration must be a boolean');

        ModulesConfig::from(
            [
                'path' => '/app/Modules',
                'generation' => ['native_bridge' => false],
                'architecture' => ['level' => 1, 'rules' => []],
            ],
            ['generation' => ['native_bridge' => 'true']],
        );
    }

    public function test_invalid_level_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('integer from 0 to 3');

        ModulesConfig::from(
            [
                'path' => '/app/Modules',
                'architecture' => ['level' => 1, 'rules' => []],
            ],
            [
                'architecture' => ['level' => '2'],
            ],
        );
    }

    public function test_invalid_baseline_path_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('baseline configuration must be a non-empty string');

        ModulesConfig::from(
            [
                'path' => '/app/Modules',
                'architecture' => [
                    'level' => 1,
                    'baseline' => '/app/moduark-baseline.json',
                    'rules' => [],
                ],
            ],
            [
                'architecture' => ['baseline' => ''],
            ],
        );
    }

    public function test_invalid_suppression_path_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('suppressions configuration must be a non-empty string');

        ModulesConfig::from(
            [
                'path' => '/app/Modules',
                'architecture' => [
                    'level' => 1,
                    'suppressions' => '/app/moduark-suppressions.json',
                    'rules' => [],
                ],
            ],
            [
                'architecture' => ['suppressions' => ''],
            ],
        );
    }
}

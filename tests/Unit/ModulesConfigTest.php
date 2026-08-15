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
}

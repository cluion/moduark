<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Generation\ModuleMakerType;
use Cluion\Moduark\Generation\NativeGeneratorBridgePlanner;
use Illuminate\Contracts\Console\Kernel;
use JsonException;
use PHPUnit\Framework\TestCase;

final class NativeGeneratorBridgeInventoryTest extends TestCase
{
    /** @throws JsonException */
    public function test_every_builtin_generator_maps_to_the_reviewed_laravel_12_and_13_inventory(): void
    {
        $planner = new NativeGeneratorBridgePlanner(
            $this->createMock(Kernel::class),
            $this->configuration(),
        );

        foreach ([12, 13] as $major) {
            $fixture = json_decode(
                (string) file_get_contents(dirname(__DIR__)."/Fixtures/Generation/laravel-{$major}.json"),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
            self::assertIsArray($fixture);
            $commands = $fixture['commands'] ?? null;
            self::assertIsArray($commands);
            $nameBased = array_filter(
                $commands,
                static function (mixed $command): bool {
                    if (! is_array($command)) {
                        return false;
                    }

                    $arguments = $command['arguments'] ?? null;

                    return is_array($arguments)
                        && in_array('name|required|single', $arguments, true);
                },
            );

            self::assertCount(31, $nameBased);
            self::assertSame(
                array_keys($nameBased),
                array_map(
                    static fn (ModuleMakerType $type): string => $type->command(),
                    $this->sortedTypes(),
                ),
            );

            foreach ($this->sortedTypes() as $type) {
                $command = $nameBased[$type->command()] ?? null;
                self::assertIsArray($command);
                self::assertSame(['name|required|single'], $command['arguments'] ?? null);
                self::assertSame($command['class'] ?? null, $planner->expectedClass($type));
            }
        }
    }

    /** @return list<ModuleMakerType> */
    private function sortedTypes(): array
    {
        $types = ModuleMakerType::cases();
        usort(
            $types,
            static fn (ModuleMakerType $left, ModuleMakerType $right): int =>
                strcmp($left->command(), $right->command()),
        );

        return $types;
    }

    private function configuration(): ModulesConfig
    {
        return ModulesConfig::from(
            [
                'path' => '/app/Modules',
                'generation' => ['native_bridge' => false],
                'architecture' => ['level' => 1, 'rules' => []],
            ],
            [],
        );
    }
}

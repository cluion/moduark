<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Cluion\Moduark\Generation\GeneratorDescriptor;
use Cluion\Moduark\Generation\GeneratorRegistry;
use Cluion\Moduark\Generation\ModuleMakerType;
use JsonException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GenerationFoundationContractTest extends TestCase
{
    /** @var list<string> */
    private const FIXTURE_GROUPS = [
        'application-framework',
        'async-types',
        'data-types',
        'http-types',
        'php-types',
        'policy',
        'presentation-types',
        'rule',
        'verification-types',
    ];

    public function test_each_laravel_inventory_candidate_is_a_built_in_generator(): void
    {
        $builtInIds = $this->builtInIds();

        self::assertCount(31, $builtInIds);

        foreach ([12, 13] as $major) {
            [$candidateIds, $tableOnlyCommands] = $this->inventory($major);

            self::assertSame($builtInIds, $candidateIds);
            self::assertSame([
                'make:cache-table',
                'make:notifications-table',
                'make:queue-batches-table',
                'make:queue-failed-table',
                'make:queue-table',
                'make:session-table',
            ], $tableOnlyCommands);
        }
    }

    public function test_laravel_twelve_and_thirteen_fixtures_cover_every_built_in_generator(): void
    {
        $laravelTwelve = $this->fixtureOwnership(12);
        $laravelThirteen = $this->fixtureOwnership(13);

        self::assertSame($laravelTwelve, $laravelThirteen);
        self::assertSame($this->builtInIds(), array_keys($laravelTwelve));
    }

    /** @return list<string> */
    private function builtInIds(): array
    {
        $registry = new GeneratorRegistry(ModuleMakerType::cases());

        return array_map(
            static fn (GeneratorDescriptor $descriptor): string => $descriptor->id(),
            $registry->all(),
        );
    }

    /** @return array{list<string>, list<string>} */
    private function inventory(int $major): array
    {
        $payload = $this->json("laravel-{$major}.json");
        $commands = $payload['commands'] ?? null;

        if (! is_array($commands)) {
            throw new RuntimeException("Laravel {$major} inventory commands must be an object.");
        }

        self::assertSame(1, $payload['schema'] ?? null);
        self::assertSame($major, $payload['laravel_major'] ?? null);
        self::assertCount(37, $commands);

        $candidateIds = [];
        $tableOnlyCommands = [];

        foreach ($commands as $command => $metadata) {
            if (! is_string($command) || ! is_array($metadata)) {
                throw new RuntimeException("Laravel {$major} inventory contains an invalid command.");
            }

            $arguments = $metadata['arguments'] ?? null;

            if (! is_array($arguments)) {
                throw new RuntimeException("Laravel {$major} command [{$command}] has invalid arguments.");
            }

            $hasNameArgument = false;

            foreach ($arguments as $argument) {
                if (! is_string($argument)) {
                    throw new RuntimeException("Laravel {$major} command [{$command}] has an invalid argument.");
                }

                $hasNameArgument = $hasNameArgument || str_starts_with($argument, 'name|');
            }

            if ($hasNameArgument) {
                $candidateIds[] = str_starts_with($command, 'make:')
                    ? substr($command, strlen('make:'))
                    : $command;
            } else {
                $tableOnlyCommands[] = $command;
            }
        }

        sort($candidateIds, SORT_STRING);
        sort($tableOnlyCommands, SORT_STRING);

        self::assertCount(31, $candidateIds);
        self::assertCount(6, $tableOnlyCommands);

        return [$candidateIds, $tableOnlyCommands];
    }

    /** @return array<string, list<string>> */
    private function fixtureOwnership(int $major): array
    {
        $fixtureRoot = dirname(__DIR__).'/Fixtures/Generation';
        $paths = glob($fixtureRoot."/*-laravel-{$major}.json");

        if ($paths === false) {
            throw new RuntimeException("Unable to enumerate Laravel {$major} Generation fixtures.");
        }

        $actualFiles = array_values(array_filter(
            array_map('basename', $paths),
            static fn (string $file): bool => $file !== "laravel-{$major}.json",
        ));
        $expectedFiles = array_map(
            static fn (string $group): string => "{$group}-laravel-{$major}.json",
            self::FIXTURE_GROUPS,
        );
        sort($actualFiles, SORT_STRING);
        sort($expectedFiles, SORT_STRING);

        self::assertSame($expectedFiles, $actualFiles);

        /** @var array<string, array<string, true>> $ownership */
        $ownership = [];

        foreach (self::FIXTURE_GROUPS as $group) {
            $payload = $this->json("{$group}-laravel-{$major}.json");
            $plans = $payload['plans'] ?? null;

            if (! is_array($plans)) {
                throw new RuntimeException("Laravel {$major} fixture group [{$group}] has invalid plans.");
            }

            self::assertSame(1, $payload['schema'] ?? null);
            self::assertSame($major, $payload['laravel_major'] ?? null);
            self::assertNotEmpty($plans);

            foreach ($plans as $plan) {
                if (! is_array($plan) || ! is_string($plan['type'] ?? null)) {
                    throw new RuntimeException("Laravel {$major} fixture group [{$group}] has an invalid plan.");
                }

                $ownership[$plan['type']][$group] = true;
            }
        }

        ksort($ownership, SORT_STRING);
        $resolved = [];

        foreach ($ownership as $type => $groups) {
            $groupNames = array_keys($groups);
            sort($groupNames, SORT_STRING);
            $resolved[$type] = $groupNames;
        }

        return $resolved;
    }

    /** @return array<string, mixed> */
    private function json(string $file): array
    {
        $path = dirname(__DIR__).'/Fixtures/Generation/'.$file;
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read Generation fixture [{$file}].");
        }

        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                "Generation fixture [{$file}] is not valid JSON.",
                previous: $exception,
            );
        }

        if (! is_array($payload)) {
            throw new RuntimeException("Generation fixture [{$file}] must be an object.");
        }

        $object = [];

        foreach ($payload as $key => $value) {
            if (! is_string($key)) {
                throw new RuntimeException("Generation fixture [{$file}] must use object keys.");
            }

            $object[$key] = $value;
        }

        return $object;
    }
}

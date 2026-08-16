<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Baseline;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class ArchitectureBaselineStore
{
    public function load(string $path): ?ArchitectureBaseline
    {
        if (! file_exists($path)) {
            return null;
        }

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Architecture baseline [{$path}] is not a readable file.");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Architecture baseline [{$path}] could not be read.");
        }

        try {
            $values = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                "Architecture baseline [{$path}] is not valid JSON: {$exception->getMessage()}",
                0,
                $exception,
            );
        }

        if (! is_array($values)) {
            throw new InvalidArgumentException("Architecture baseline [{$path}] must contain a JSON object.");
        }

        /** @var array<string, mixed> $values */
        return ArchitectureBaseline::fromArray($values);
    }

    public function write(string $path, ArchitectureBaseline $baseline): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new RuntimeException("Architecture baseline directory [{$directory}] is not writable.");
        }

        $json = json_encode(
            $baseline->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ).PHP_EOL;
        $temporary = tempnam($directory, '.moduark-baseline-');

        if ($temporary === false) {
            throw new RuntimeException("A temporary architecture baseline could not be created in [{$directory}].");
        }

        try {
            if (file_put_contents($temporary, $json, LOCK_EX) === false || ! rename($temporary, $path)) {
                throw new RuntimeException("Architecture baseline [{$path}] could not be written.");
            }
        } finally {
            if (file_exists($temporary)) {
                unlink($temporary);
            }
        }
    }
}

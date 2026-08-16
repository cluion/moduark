<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Suppression;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class SuppressionManifestStore
{
    public function load(string $path): ?SuppressionManifest
    {
        if (! file_exists($path)) {
            return null;
        }

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Suppression manifest [{$path}] is not a readable file.");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Suppression manifest [{$path}] could not be read.");
        }

        try {
            $values = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                "Suppression manifest [{$path}] is not valid JSON: {$exception->getMessage()}",
                0,
                $exception,
            );
        }

        if (! is_array($values)) {
            throw new InvalidArgumentException("Suppression manifest [{$path}] must contain a JSON object.");
        }

        /** @var array<string, mixed> $values */
        return SuppressionManifest::fromArray($values);
    }
}

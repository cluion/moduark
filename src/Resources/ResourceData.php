<?php

declare(strict_types=1);

namespace Cluion\Moduark\Resources;

use Cluion\Moduark\Exceptions\ResourceManifestFailed;

final class ResourceData
{
    /**
     * @return scalar|array<mixed>|null
     */
    public static function normalize(mixed $value, string $context): mixed
    {
        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if (! is_array($value)) {
            throw ResourceManifestFailed::invalidData($context);
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            $normalized[$key] = self::normalize($item, $context.'.'.$key);
        }

        if (! array_is_list($normalized)) {
            ksort($normalized, SORT_STRING);
        }

        return $normalized;
    }

    /**
     * @param array<mixed> $value
     * @return array<string, mixed>
     */
    public static function normalizeMap(array $value, string $context): array
    {
        $normalized = self::normalize($value, $context);

        if (! is_array($normalized)) {
            throw ResourceManifestFailed::invalidData($context);
        }

        $map = [];

        foreach ($normalized as $key => $item) {
            if (! is_string($key)) {
                throw ResourceManifestFailed::invalidData($context);
            }

            $map[$key] = $item;
        }

        return $map;
    }

    private function __construct()
    {
    }
}

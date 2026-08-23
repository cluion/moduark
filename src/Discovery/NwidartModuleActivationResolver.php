<?php

declare(strict_types=1);

namespace Cluion\Moduark\Discovery;

use JsonException;
use RuntimeException;

final class NwidartModuleActivationResolver
{
    public function resolve(object $activator, string $modulesPath): ModuleActivationSet
    {
        if (! method_exists($activator, 'hasStatus')) {
            throw new RuntimeException(
                'The nwidart Module activator must expose hasStatus().',
            );
        }

        if (! is_dir($modulesPath)) {
            return ModuleActivationSet::only([]);
        }

        $directories = glob(
            rtrim($modulesPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*',
            GLOB_ONLYDIR,
        );

        if ($directories === false) {
            throw new RuntimeException(
                "Unable to scan nwidart Module path [{$modulesPath}].",
            );
        }

        $names = [];

        foreach ($directories as $directory) {
            $name = basename($directory);
            $active = $activator->hasStatus($name, true);

            if (! is_bool($active)) {
                throw new RuntimeException(
                    'The nwidart Module activator returned an invalid status.',
                );
            }

            if ($active) {
                $names[] = $name;
            }
        }

        return ModuleActivationSet::only($names);
    }

    public function resolveFile(string $statusesPath, string $modulesPath): ModuleActivationSet
    {
        if (! is_file($statusesPath)) {
            return ModuleActivationSet::only([]);
        }

        $contents = file_get_contents($statusesPath);

        if ($contents === false) {
            throw new RuntimeException("Unable to read nwidart Module statuses [{$statusesPath}].");
        }

        try {
            $statuses = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                "The nwidart Module statuses [{$statusesPath}] are invalid.",
                previous: $exception,
            );
        }

        if (! is_array($statuses)) {
            throw new RuntimeException("The nwidart Module statuses [{$statusesPath}] are invalid.");
        }

        $activator = new class($statuses)
        {
            /** @param array<mixed> $statuses */
            public function __construct(private readonly array $statuses)
            {
            }

            public function hasStatus(string $name, bool $status): bool
            {
                $actual = $this->statuses[$name] ?? false;

                return is_bool($actual) && $actual === $status;
            }
        };

        foreach ($statuses as $name => $status) {
            if (! is_string($name) || $name === '' || ! is_bool($status)) {
                throw new RuntimeException("The nwidart Module statuses [{$statusesPath}] are invalid.");
            }
        }

        return $this->resolve($activator, $modulesPath);
    }
}

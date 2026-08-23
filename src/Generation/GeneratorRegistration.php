<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class GeneratorRegistration
{
    public static function register(
        Container $container,
        GeneratorDescriptor|string $descriptor,
    ): void {
        if (is_string($descriptor) && ! is_a($descriptor, GeneratorDescriptor::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'Generator descriptor [%s] must implement %s.',
                $descriptor,
                GeneratorDescriptor::class,
            ));
        }

        $container->extend(
            GeneratorRegistry::class,
            static function (GeneratorRegistry $registry, Container $container) use ($descriptor): GeneratorRegistry {
                $resolved = is_string($descriptor)
                    ? $container->make($descriptor)
                    : $descriptor;

                $registry->register($resolved);

                return $registry;
            },
        );
    }

    private function __construct()
    {
    }
}

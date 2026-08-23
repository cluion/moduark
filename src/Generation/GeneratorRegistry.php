<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use Cluion\Moduark\Exceptions\ModuleMakerFailed;
use InvalidArgumentException;

final class GeneratorRegistry
{
    /** @var array<string, GeneratorDescriptor> */
    private array $descriptors = [];

    /** @param iterable<GeneratorDescriptor> $descriptors */
    public function __construct(iterable $descriptors = [])
    {
        foreach ($descriptors as $descriptor) {
            $this->register($descriptor);
        }
    }

    public function register(GeneratorDescriptor $descriptor): void
    {
        $id = $descriptor->id();

        if (preg_match('/\A[a-z][a-z0-9]*(?:-[a-z0-9]+)*\z/D', $id) !== 1) {
            throw new InvalidArgumentException(
                "Generator ID [{$id}] must be canonical lowercase kebab-case.",
            );
        }

        if (isset($this->descriptors[$id])) {
            throw new InvalidArgumentException("Generator ID [{$id}] is already registered.");
        }

        $this->descriptors[$id] = $descriptor;
    }

    public function resolve(string $id): GeneratorDescriptor
    {
        $canonical = strtolower($id);

        return $this->descriptors[$canonical] ?? throw ModuleMakerFailed::unsupportedType($id);
    }

    /** @return list<GeneratorDescriptor> */
    public function all(): array
    {
        $descriptors = $this->descriptors;
        ksort($descriptors, SORT_STRING);

        return array_values($descriptors);
    }
}

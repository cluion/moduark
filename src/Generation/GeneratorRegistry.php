<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use Cluion\Moduark\Exceptions\ModuleMakerFailed;
use InvalidArgumentException;

final class GeneratorRegistry
{
    /** @var list<string> */
    private const SUPPORTED_OPTIONS = [
        'api',
        'batched',
        'collection',
        'create',
        'event',
        'extension',
        'factory',
        'force',
        'guard',
        'implicit',
        'inbound',
        'inline',
        'int',
        'invokable',
        'json-api',
        'markdown',
        'migration',
        'model',
        'path',
        'pest',
        'phpunit',
        'queued',
        'render',
        'report',
        'resource',
        'string',
        'sync',
        'table',
        'test',
        'unit',
        'view',
    ];

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

        if (! $descriptor instanceof ModuleMakerType && ModuleMakerType::tryFrom($id) !== null) {
            throw new InvalidArgumentException(
                "Generator ID [{$id}] is reserved for a built-in descriptor.",
            );
        }

        $options = $descriptor->supportedOptions();

        if (count($options) !== count(array_unique($options))) {
            throw new InvalidArgumentException(
                "Generator [{$id}] must not declare duplicate supported options.",
            );
        }

        foreach ($options as $option) {
            if (! in_array($option, self::SUPPORTED_OPTIONS, true)) {
                throw new InvalidArgumentException(
                    "Generator [{$id}] declares unknown supported option [{$option}].",
                );
            }
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

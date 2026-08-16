<?php

declare(strict_types=1);

namespace Cluion\Moduark\Cache;

use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use InvalidArgumentException;

final readonly class ModuleCacheManifest
{
    public const SCHEMA_VERSION = 1;

    /**
     * @param list<ModuleDescriptor> $descriptors
     */
    public function __construct(
        private string $modulesPath,
        private ModuleRegistry $registry,
        private array $descriptors,
    ) {
        if ($this->modulesPath === '') {
            throw new InvalidArgumentException('The cached Module path must be a non-empty string.');
        }

        $registryClasses = $this->registry->moduleClasses();
        $descriptorClasses = array_map(
            static fn (ModuleDescriptor $descriptor): string => $descriptor->moduleClass(),
            $this->descriptors,
        );

        if (count($descriptorClasses) !== count(array_unique($descriptorClasses))) {
            throw new InvalidArgumentException('The Module cache contains duplicate descriptors.');
        }

        sort($registryClasses, SORT_STRING);
        sort($descriptorClasses, SORT_STRING);

        if ($registryClasses !== $descriptorClasses) {
            throw new InvalidArgumentException('The Module cache registry and descriptors do not match.');
        }
    }

    /**
     * @param array<mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        if (($payload['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('The Module cache schema is invalid.');
        }

        $modulesPath = $payload['modules_path'] ?? null;
        $registryRows = $payload['registry'] ?? null;
        $descriptorRows = $payload['descriptors'] ?? null;

        if (! is_string($modulesPath) || $modulesPath === ''
            || ! is_array($registryRows)
            || ! is_array($descriptorRows)) {
            throw new InvalidArgumentException('The Module cache payload is invalid.');
        }

        $modules = [];

        foreach ($registryRows as $row) {
            if (! is_array($row)) {
                throw new InvalidArgumentException('The Module cache registry is invalid.');
            }

            $name = $row['name'] ?? null;
            $moduleClass = $row['class'] ?? null;
            $path = $row['path'] ?? null;
            $namespace = $row['namespace'] ?? null;

            if (! is_string($name) || $name === ''
                || ! is_string($moduleClass) || $moduleClass === ''
                || ! is_string($path) || $path === ''
                || ! is_string($namespace) || $namespace === '') {
                throw new InvalidArgumentException('The Module cache registry is invalid.');
            }

            /** @var class-string<Module> $moduleClass */
            $modules[] = new DiscoveredModule(
                $name,
                $moduleClass,
                $path,
                $namespace,
            );
        }

        $descriptors = [];

        foreach ($descriptorRows as $row) {
            if (! is_array($row) || ! self::validDescriptor($row)) {
                throw new InvalidArgumentException('The Module cache descriptors are invalid.');
            }

            /** @var array{
             *     module: class-string<Module>,
             *     dependencies: list<class-string<Module>>,
             *     providers: list<class-string<\Illuminate\Support\ServiceProvider>>,
             *     requires: list<array{
             *         capability: class-string<\Cluion\Moduark\Capability>,
             *         port: class-string,
             *         adapter: class-string
             *     }>,
             *     provides: list<class-string<\Cluion\Moduark\Capability>>
             * } $row */
            $descriptors[] = ModuleDescriptor::fromArray($row);
        }

        return new self($modulesPath, new ModuleRegistry($modules), $descriptors);
    }

    public function modulesPath(): string
    {
        return $this->modulesPath;
    }

    public function registry(): ModuleRegistry
    {
        return $this->registry;
    }

    /**
     * @return list<ModuleDescriptor>
     */
    public function descriptors(): array
    {
        return $this->descriptors;
    }

    /**
     * @return array{
     *     schema_version: int,
     *     modules_path: string,
     *     registry: list<array{name: string, class: class-string<Module>, path: string, namespace: string}>,
     *     descriptors: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'modules_path' => $this->modulesPath,
            'registry' => $this->registry->toArray(),
            'descriptors' => array_map(
                static fn (ModuleDescriptor $descriptor): array => $descriptor->toArray(),
                $this->descriptors,
            ),
        ];
    }

    /**
     * @param array<mixed> $values
     * @param list<string> $keys
     */
    private static function hasStringKeys(array $values, array $keys): bool
    {
        foreach ($keys as $key) {
            if (! isset($values[$key]) || ! is_string($values[$key]) || $values[$key] === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<mixed> $values
     */
    private static function validDescriptor(array $values): bool
    {
        if (! self::hasStringKeys($values, ['module'])
            || ! self::isStringList($values['dependencies'] ?? null)
            || ! self::isStringList($values['providers'] ?? null)
            || ! self::isStringList($values['provides'] ?? null)
            || ! is_array($values['requires'] ?? null)) {
            return false;
        }

        foreach ($values['requires'] as $requirement) {
            if (! is_array($requirement)
                || ! self::hasStringKeys($requirement, ['capability', 'port', 'adapter'])) {
                return false;
            }
        }

        return true;
    }

    private static function isStringList(mixed $values): bool
    {
        if (! is_array($values) || ! array_is_list($values)) {
            return false;
        }

        foreach ($values as $value) {
            if (! is_string($value) || $value === '') {
                return false;
            }
        }

        return true;
    }
}

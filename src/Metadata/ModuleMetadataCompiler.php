<?php

declare(strict_types=1);

namespace Cluion\Moduark\Metadata;

use Cluion\Moduark\Capability;
use Cluion\Moduark\CapabilityRequirement;
use Cluion\Moduark\Exceptions\InvalidModuleMetadata;
use Cluion\Moduark\Module;
use Cluion\Moduark\Persistence\TableName;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

final class ModuleMetadataCompiler
{
    /**
     * @var array<class-string<Module>, ModuleDescriptor>
     */
    private array $cached = [];

    /**
     * @param list<ModuleDescriptor> $cached
     */
    public function __construct(array $cached = [])
    {
        foreach ($cached as $descriptor) {
            $moduleClass = $descriptor->moduleClass();

            if (isset($this->cached[$moduleClass])) {
                throw InvalidModuleMetadata::duplicateModule($moduleClass);
            }

            $this->cached[$moduleClass] = $descriptor;
        }
    }

    public function compile(string $moduleClass): ModuleDescriptor
    {
        if (isset($this->cached[$moduleClass])) {
            return $this->cached[$moduleClass];
        }

        if (! is_a($moduleClass, Module::class, true)) {
            throw InvalidModuleMetadata::invalidModuleClass($moduleClass);
        }

        $module = new $moduleClass;

        return new ModuleDescriptor(
            $moduleClass,
            $this->moduleClasses($moduleClass, 'dependencies', $module->dependencies()),
            $this->providerClasses($moduleClass, $module->providers()),
            $this->requirements($moduleClass, $module->requires()),
            $this->capabilityClasses($moduleClass, 'provides', $module->provides()),
            $this->tableNames($moduleClass, $module->tables()),
        );
    }

    /**
     * @param list<string> $moduleClasses
     * @return list<ModuleDescriptor>
     */
    public function compileAll(array $moduleClasses): array
    {
        $compiled = [];
        $seen = [];

        foreach ($moduleClasses as $moduleClass) {
            if (isset($seen[$moduleClass])) {
                throw InvalidModuleMetadata::duplicateModule($moduleClass);
            }

            $seen[$moduleClass] = true;
            $compiled[] = $this->compile($moduleClass);
        }

        return $compiled;
    }

    /**
     * @param array<mixed> $values
     * @return list<class-string<Module>>
     */
    private function moduleClasses(string $moduleClass, string $method, array $values): array
    {
        $classes = [];

        foreach ($values as $value) {
            if (! is_string($value) || ! is_a($value, Module::class, true)) {
                throw InvalidModuleMetadata::invalidClassReference(
                    $moduleClass,
                    $method,
                    $value,
                    'class-string<'.Module::class.'>',
                );
            }

            if (isset($classes[$value])) {
                throw InvalidModuleMetadata::duplicateReference($moduleClass, $method, $value);
            }

            $classes[$value] = $value;
        }

        return array_values($classes);
    }

    /**
     * @param array<mixed> $values
     * @return list<class-string<ServiceProvider>>
     */
    private function providerClasses(string $moduleClass, array $values): array
    {
        $classes = [];

        foreach ($values as $value) {
            if (! is_string($value) || ! is_a($value, ServiceProvider::class, true)) {
                throw InvalidModuleMetadata::invalidClassReference(
                    $moduleClass,
                    'providers',
                    $value,
                    'class-string<'.ServiceProvider::class.'>',
                );
            }

            if (isset($classes[$value])) {
                throw InvalidModuleMetadata::duplicateReference($moduleClass, 'providers', $value);
            }

            $classes[$value] = $value;
        }

        return array_values($classes);
    }

    /**
     * @param array<mixed> $values
     * @return list<CapabilityRequirement>
     */
    private function requirements(string $moduleClass, array $values): array
    {
        $requirements = [];
        $capabilities = [];
        $ports = [];

        foreach ($values as $value) {
            if (! $value instanceof CapabilityRequirement) {
                throw InvalidModuleMetadata::invalidCapabilityRequirement($moduleClass, $value);
            }

            $capability = $this->capabilityClass(
                $moduleClass,
                'requires',
                $value->capability(),
            );
            $port = $this->portClass($moduleClass, $value->port());
            $adapter = $this->adapterClass($moduleClass, $value->adapter(), $port);

            if (isset($capabilities[$capability])) {
                throw InvalidModuleMetadata::duplicateReference(
                    $moduleClass,
                    'requires',
                    $capability,
                );
            }

            if (isset($ports[$port])) {
                throw InvalidModuleMetadata::duplicateCapabilityPort($moduleClass, $port);
            }

            $capabilities[$capability] = true;
            $ports[$port] = true;
            $requirements[] = new CapabilityRequirement($capability, $port, $adapter);
        }

        return $requirements;
    }

    /**
     * @param array<mixed> $values
     * @return list<class-string<Capability>>
     */
    private function capabilityClasses(string $moduleClass, string $method, array $values): array
    {
        $classes = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                throw InvalidModuleMetadata::invalidClassReference(
                    $moduleClass,
                    $method,
                    $value,
                    'class-string<'.Capability::class.'>',
                );
            }

            $capability = $this->capabilityClass($moduleClass, $method, $value);

            if (isset($classes[$capability])) {
                throw InvalidModuleMetadata::duplicateReference($moduleClass, $method, $capability);
            }

            $classes[$capability] = $capability;
        }

        return array_values($classes);
    }

    /**
     * @return class-string<Capability>
     */
    private function capabilityClass(string $moduleClass, string $method, string $value): string
    {
        if ($value === Capability::class || ! is_a($value, Capability::class, true)) {
            throw InvalidModuleMetadata::invalidClassReference(
                $moduleClass,
                $method,
                $value,
                'class-string extending '.Capability::class,
            );
        }

        return $value;
    }

    /**
     * @return class-string
     */
    private function portClass(string $moduleClass, string $value): string
    {
        if (! interface_exists($value)) {
            throw InvalidModuleMetadata::invalidCapabilityPort($moduleClass, $value);
        }

        return $value;
    }

    /**
     * @param class-string $port
     * @return class-string
     */
    private function adapterClass(string $moduleClass, string $value, string $port): string
    {
        if (! class_exists($value) || ! is_a($value, $port, true)) {
            throw InvalidModuleMetadata::invalidCapabilityAdapter($moduleClass, $value, $port);
        }

        $adapter = new ReflectionClass($value);

        if (! $adapter->isInstantiable()) {
            throw InvalidModuleMetadata::invalidCapabilityAdapter($moduleClass, $value, $port);
        }

        return $value;
    }

    /**
     * @param array<mixed> $values
     * @return list<string>
     */
    private function tableNames(string $moduleClass, array $values): array
    {
        $tables = [];

        foreach ($values as $value) {
            if (! is_string($value) || ! TableName::valid($value)) {
                throw InvalidModuleMetadata::invalidTableName($moduleClass, $value);
            }

            $key = TableName::key($value);

            if (isset($tables[$key])) {
                throw InvalidModuleMetadata::duplicateReference($moduleClass, 'tables', $value);
            }

            $tables[$key] = $value;
        }

        return array_values($tables);
    }
}

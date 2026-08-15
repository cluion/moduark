<?php

declare(strict_types=1);

namespace Cluion\Moduark\Metadata;

use Cluion\Moduark\Exceptions\InvalidModuleMetadata;
use Cluion\Moduark\Module;
use Illuminate\Support\ServiceProvider;

final class ModuleMetadataCompiler
{
    public function compile(string $moduleClass): ModuleDescriptor
    {
        if (! is_a($moduleClass, Module::class, true)) {
            throw InvalidModuleMetadata::invalidModuleClass($moduleClass);
        }

        $module = new $moduleClass;

        return new ModuleDescriptor(
            $moduleClass,
            $this->moduleClasses($moduleClass, 'dependencies', $module->dependencies()),
            $this->providerClasses($moduleClass, $module->providers()),
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
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Extraction;

use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Registry\ModuleRegistry;
use Cluion\Moduark\Resources\ResourceDescriptor;
use Cluion\Moduark\Resources\ResourceManifest;
use Composer\Autoload\ClassLoader;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;

final readonly class ExtractabilityInspector
{
    public function __construct(
        private ModuleRegistry $registry,
        private ModuleMetadataCompiler $compiler,
        private ResourceManifest $resources,
        private ModulesConfig $configuration,
        private string $applicationVendorPath,
        private ArchitectureExtractabilityGate $architecture,
    ) {
    }

    public function inspect(string $name): ExtractabilityReport
    {
        $module = $this->registry->find($name);

        if ($module === null) {
            throw new InvalidArgumentException("Module [{$name}] is not active or does not exist.");
        }

        $metadata = $this->compiler->compile($module->moduleClass());

        return new ExtractabilityReport($module, [
            $this->layout($module),
            $this->autoload($module),
            $this->providerOwnership($module, $metadata),
            $this->resourceOwnership($module),
            $this->declaredMetadataCoupling($module, $metadata),
            ...$this->architecture->checks($module),
        ]);
    }

    private function layout(DiscoveredModule $module): ExtractabilityCheck
    {
        $entry = $module->path();
        $layout = $this->moduleLayout($module);

        if (! is_file($entry) || $layout === null) {
            return $this->blocked(
                'MOD-EXTRACT-LAYOUT-001',
                'layout',
                'The Module entry does not use a supported export source layout.',
                [$entry],
            );
        }

        return $this->passed(
            'MOD-EXTRACT-LAYOUT-001',
            'layout',
            "The Module entry uses the supported {$layout} layout.",
            ["entry={$entry}", "layout={$layout}"],
        );
    }

    private function autoload(DiscoveredModule $module): ExtractabilityCheck
    {
        $autoloaded = $this->classFile($module->moduleClass());

        if ($autoloaded === null || ! $this->samePath($module->path(), $autoloaded)) {
            return $this->blocked(
                'MOD-EXTRACT-AUTOLOAD-001',
                'autoload',
                'The Module entry class does not autoload from its discovered source file.',
                [
                    'discovered='.$module->path(),
                    'autoloaded='.($autoloaded ?? '[internal-or-unavailable]'),
                ],
            );
        }

        return $this->passed(
            'MOD-EXTRACT-AUTOLOAD-001',
            'autoload',
            'The Module entry class autoloads from its discovered source file.',
            [$module->moduleClass().'='.$autoloaded],
        );
    }

    private function providerOwnership(
        DiscoveredModule $module,
        ModuleDescriptor $metadata,
    ): ExtractabilityCheck {
        $root = $this->sourceRoot($module);
        $outside = [];

        foreach ($metadata->providers() as $provider) {
            $file = $this->classFile($provider);

            if ($file === null || ! $this->within($file, $root)) {
                $outside[] = $provider.'='.($file ?? '[internal-or-unavailable]');
            }
        }

        if ($outside !== []) {
            return $this->blocked(
                'MOD-EXTRACT-PROVIDER-001',
                'provider_ownership',
                'Every Module provider must be owned by the Module source root.',
                $outside,
            );
        }

        return $this->passed(
            'MOD-EXTRACT-PROVIDER-001',
            'provider_ownership',
            'Every declared provider is owned by the Module source root.',
            $metadata->providers() === [] ? ['providers=none'] : $metadata->providers(),
        );
    }

    private function resourceOwnership(DiscoveredModule $module): ExtractabilityCheck
    {
        $root = $this->moduleRoot($module);
        $outside = [];
        $owned = [];

        foreach ($this->resources->forModule($module->moduleClass()) as $resource) {
            $source = $resource->sourcePath();

            if ($source === null) {
                continue;
            }

            $evidence = $this->resourceEvidence($resource);

            if ((! is_file($source) && ! is_dir($source)) || ! $this->within($source, $root)) {
                $outside[] = $evidence;
            } else {
                $owned[] = $evidence;
            }
        }

        if ($outside !== []) {
            return $this->blocked(
                'MOD-EXTRACT-RESOURCE-001',
                'resource_ownership',
                'Every file-backed resource must be owned by the Module root.',
                $outside,
            );
        }

        return $this->passed(
            'MOD-EXTRACT-RESOURCE-001',
            'resource_ownership',
            'Every file-backed resource is owned by the Module root.',
            $owned === [] ? ['file_resources=none'] : $owned,
        );
    }

    private function declaredMetadataCoupling(
        DiscoveredModule $module,
        ModuleDescriptor $metadata,
    ): ExtractabilityCheck {
        $classes = [...$metadata->provides(), ...$metadata->exports()];

        foreach ($metadata->requires() as $requirement) {
            $classes[] = $requirement->capability();
            $classes[] = $requirement->port();
            $classes[] = $requirement->adapter();
        }

        $classes = array_values(array_unique($classes));
        sort($classes, SORT_STRING);
        $moduleRoots = array_map(
            fn (DiscoveredModule $candidate): string => $this->sourceRoot($candidate),
            $this->registry->all(),
        );
        $externalRoots = $this->externalRoots();
        $couplings = [];

        foreach ($classes as $class) {
            $file = $this->classFile($class);

            if ($file === null
                || $this->withinAny($file, $moduleRoots)
                || $this->withinAny($file, $externalRoots)) {
                continue;
            }

            $couplings[] = $class.'='.$file;
        }

        if ($couplings !== []) {
            return $this->blocked(
                'MOD-EXTRACT-COUPLING-001',
                'application_coupling',
                'Declared metadata references application code outside every active Module.',
                $couplings,
            );
        }

        return $this->passed(
            'MOD-EXTRACT-COUPLING-001',
            'application_coupling',
            'Declared metadata has no application-global class coupling.',
            $classes === [] ? ['declared_classes=none'] : $classes,
        );
    }

    /** @return list<string> */
    private function externalRoots(): array
    {
        $roots = [$this->applicationVendorPath];
        $loaderFile = (new ReflectionClass(ClassLoader::class))->getFileName();

        if (is_string($loaderFile)) {
            $roots[] = dirname($loaderFile, 2);
        }

        return array_values(array_unique($roots));
    }

    private function moduleLayout(DiscoveredModule $module): ?string
    {
        $entry = $module->path();
        $base = rtrim($this->configuration->path(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.$module->name();

        if ($this->samePath($entry, $base.DIRECTORY_SEPARATOR.$module->name().'Module.php')) {
            return 'standalone';
        }

        if ($this->samePath(
            $entry,
            $base.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.$module->name().'Module.php',
        )) {
            return 'nwidart';
        }

        return null;
    }

    private function sourceRoot(DiscoveredModule $module): string
    {
        return dirname($module->path());
    }

    private function moduleRoot(DiscoveredModule $module): string
    {
        $sourceRoot = $this->sourceRoot($module);

        return $this->moduleLayout($module) === 'nwidart'
            ? dirname($sourceRoot)
            : $sourceRoot;
    }

    /** @param class-string $class */
    private function classFile(string $class): ?string
    {
        try {
            $file = (new ReflectionClass($class))->getFileName();
        } catch (ReflectionException) {
            return null;
        }

        return is_string($file) ? $file : null;
    }

    private function resourceEvidence(ResourceDescriptor $resource): string
    {
        return $resource->plugin().':'.$resource->identity().'='.$resource->sourcePath();
    }

    /** @param list<string> $roots */
    private function withinAny(string $path, array $roots): bool
    {
        foreach ($roots as $root) {
            if ($this->within($path, $root)) {
                return true;
            }
        }

        return false;
    }

    private function within(string $path, string $root): bool
    {
        $path = $this->normalizePath($path);
        $root = rtrim($this->normalizePath($root), '/');

        if (PHP_OS_FAMILY === 'Windows') {
            $path = strtolower($path);
            $root = strtolower($root);
        }

        return $path === $root || str_starts_with($path, $root.'/');
    }

    private function samePath(string $left, string $right): bool
    {
        $left = $this->normalizePath($left);
        $right = $this->normalizePath($right);

        return PHP_OS_FAMILY === 'Windows'
            ? strtolower($left) === strtolower($right)
            : $left === $right;
    }

    private function normalizePath(string $path): string
    {
        $realPath = realpath($path);

        return str_replace('\\', '/', $realPath === false ? $path : $realPath);
    }

    /** @param list<string> $evidence */
    private function passed(
        string $code,
        string $category,
        string $message,
        array $evidence,
    ): ExtractabilityCheck {
        return new ExtractabilityCheck(
            $code,
            $category,
            ExtractabilityCheck::PASSED,
            $message,
            $evidence,
        );
    }

    /** @param list<string> $evidence */
    private function blocked(
        string $code,
        string $category,
        string $message,
        array $evidence,
    ): ExtractabilityCheck {
        return new ExtractabilityCheck(
            $code,
            $category,
            ExtractabilityCheck::BLOCKED,
            $message,
            $evidence,
        );
    }
}

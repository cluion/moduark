<?php

declare(strict_types=1);

namespace Cluion\Moduark\Export;

use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Extraction\ExtractabilityInspector;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Registry\ModuleRegistry;
use InvalidArgumentException;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final readonly class ModuleExportPlanner
{
    private const EXCLUDED_ROOTS = ['.git', 'node_modules', 'vendor'];

    public function __construct(
        private ModuleRegistry $registry,
        private ModuleMetadataCompiler $compiler,
        private ExtractabilityInspector $extractability,
        private ModulesConfig $configuration,
        private string $applicationBasePath,
    ) {
    }

    public function plan(
        string $moduleName,
        string $target,
        string $package,
        string $namespace,
    ): ModuleExportPlan {
        $target = $this->target($target);
        $package = $this->package($package);
        $namespace = $this->namespace($namespace);
        $report = $this->extractability->inspect($moduleName);
        $module = $report->module();
        $provider = $namespace.'\\'.$module->name().'PackageServiceProvider';
        $blockers = $this->targetBlockers($target);

        if (! $report->readyForExportDryRun()) {
            $blockers[] = new ExportPlanBlocker(
                'MOD-EXPORT-EXTRACTABILITY-001',
                'The Module extractability gate must pass before files can be planned.',
                array_map(
                    static fn ($check): string => $check->code(),
                    $report->blockers(),
                ),
            );

            return new ModuleExportPlan(
                $module,
                $target,
                $package,
                $namespace,
                $provider,
                [],
                [],
                $blockers,
            );
        }

        $layout = $this->layout($module);
        $root = $this->moduleRoot($module);
        [$files, $sourceEvidence] = $this->files($module, $layout, $root, $namespace, $provider);

        if ($sourceEvidence !== []) {
            $blockers[] = new ExportPlanBlocker(
                'MOD-EXPORT-SOURCE-001',
                'The Module source inventory contains a linked or unsupported entry.',
                $sourceEvidence,
            );
        }

        $dependencies = $this->dependencies($module);
        $manual = [];

        foreach ($dependencies as $dependency) {
            if ($dependency->status() === ExportPlanDependency::MANUAL) {
                $manual[] = $dependency->source();
            }
        }

        if ($manual !== []) {
            $blockers[] = new ExportPlanBlocker(
                'MOD-EXPORT-DEPENDENCY-001',
                'Every Module dependency needs an explicit Composer package and constraint mapping.',
                $manual,
            );
        }

        $collisions = $this->collisions($target, $files);

        if ($collisions !== []) {
            $blockers[] = new ExportPlanBlocker(
                'MOD-EXPORT-COLLISION-001',
                'One or more planned destination files already exist or collide.',
                $collisions,
            );
        }

        return new ModuleExportPlan(
            $module,
            $target,
            $package,
            $namespace,
            $provider,
            $files,
            $dependencies,
            $blockers,
        );
    }

    private function target(string $target): string
    {
        if (trim($target) !== $target || $target === '' || str_contains($target, '\\')) {
            throw new InvalidArgumentException('The export target must be a portable application-relative path.');
        }

        $segments = explode('/', $target);

        if (str_starts_with($target, '/')
            || preg_match('/\A[A-Za-z]:\//', $target) === 1
            || in_array('', $segments, true)
            || in_array('.', $segments, true)
            || in_array('..', $segments, true)) {
            throw new InvalidArgumentException('The export target must be a portable application-relative path.');
        }

        return implode('/', $segments);
    }

    private function package(string $package): string
    {
        if (preg_match('/\A[a-z0-9](?:[a-z0-9._-]*[a-z0-9])?\/[a-z0-9](?:[a-z0-9._-]*[a-z0-9])?\z/', $package) !== 1) {
            throw new InvalidArgumentException('The export package must be a lowercase Composer vendor/name.');
        }

        return $package;
    }

    private function namespace(string $namespace): string
    {
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*\z/', $namespace) !== 1) {
            throw new InvalidArgumentException('The export namespace must be a valid PHP namespace without leading or trailing separators.');
        }

        return $namespace;
    }

    /** @return list<ExportPlanBlocker> */
    private function targetBlockers(string $target): array
    {
        $absolute = $this->targetAbsolutePath($target);
        $evidence = [];

        if (file_exists($absolute) && ! is_dir($absolute)) {
            $evidence[] = $target.'=not_directory';
        }

        $cursor = $absolute;
        $base = $this->normalize($this->applicationBasePath);

        while ($cursor !== $base && str_starts_with($cursor, $base.'/')) {
            if (is_link($cursor)) {
                $evidence[] = $this->relativeToBase($cursor).'=symlink';
            }

            $parent = dirname($cursor);

            if ($parent === $cursor) {
                break;
            }

            $cursor = $parent;
        }

        return $evidence === [] ? [] : [new ExportPlanBlocker(
            'MOD-EXPORT-TARGET-001',
            'The export target must resolve through ordinary directories inside the application.',
            $evidence,
        )];
    }

    /** @return array{list<ExportPlanFile>, list<string>} */
    private function files(
        DiscoveredModule $module,
        string $layout,
        string $root,
        string $namespace,
        string $provider,
    ): array {
        $files = [
            new ExportPlanFile('generate', null, 'composer.json', 'composer_metadata'),
            new ExportPlanFile(
                'generate',
                null,
                'src/'.$module->name().'PackageServiceProvider.php',
                'package_provider:'.$provider,
            ),
        ];
        $unsafe = [];
        $directory = new RecursiveDirectoryIterator(
            $root,
            RecursiveDirectoryIterator::SKIP_DOTS,
        );
        $filter = new RecursiveCallbackFilterIterator(
            $directory,
            function (SplFileInfo $entry) use ($root): bool {
                $relative = $this->relative($root, $entry->getPathname());
                $first = explode('/', $relative, 2)[0];

                return ! in_array($first, self::EXCLUDED_ROOTS, true);
            },
        );
        $iterator = new RecursiveIteratorIterator(
            $filter,
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $entry) {
            if (! $entry instanceof SplFileInfo) {
                continue;
            }

            $source = $this->relative($root, $entry->getPathname());

            if ($entry->isLink()) {
                $unsafe[] = $source.'=symlink';

                continue;
            }

            if ($entry->isDir()) {
                continue;
            }

            if (! $entry->isFile()) {
                $unsafe[] = $source.'=unsupported';

                continue;
            }

            if (in_array($source, ['composer.json', 'module.json'], true)) {
                continue;
            }

            $destination = $this->destination($source, $layout);
            $transform = str_ends_with(strtolower($source), '.php')
                ? 'namespace:'.$module->namespace().'=>'.$namespace
                : null;
            $files[] = new ExportPlanFile('copy', $source, $destination, $transform);
        }

        return [$files, $unsafe];
    }

    private function destination(string $source, string $layout): string
    {
        if ($layout === 'nwidart' && str_starts_with($source, 'app/')) {
            return 'src/'.substr($source, 4);
        }

        foreach (['config', 'resources', 'routes'] as $root) {
            if ($source === $root || str_starts_with($source, $root.'/')) {
                return $source;
            }
        }

        foreach (['Tests/', 'tests/'] as $prefix) {
            if (str_starts_with($source, $prefix)) {
                return 'tests/'.substr($source, strlen($prefix));
            }
        }

        foreach (['Database/Migrations/', 'database/migrations/'] as $prefix) {
            if (str_starts_with($source, $prefix)) {
                return 'database/migrations/'.substr($source, strlen($prefix));
            }
        }

        foreach (['database/factories/' => 'Database/Factories/', 'database/seeders/' => 'Database/Seeders/'] as $prefix => $target) {
            if (str_starts_with($source, $prefix)) {
                return 'src/'.$target.substr($source, strlen($prefix));
            }
        }

        if (in_array($source, ['package.json', 'vite.config.js'], true)) {
            return $source;
        }

        return 'src/'.$source;
    }

    /** @return list<ExportPlanDependency> */
    private function dependencies(DiscoveredModule $module): array
    {
        $dependencies = [
            new ExportPlanDependency(
                'runtime',
                'cluion/moduark',
                'cluion/moduark',
                '^1.3',
                ExportPlanDependency::RESOLVED,
            ),
            new ExportPlanDependency(
                'runtime',
                'illuminate/support',
                'illuminate/support',
                '^12.0 || ^13.0',
                ExportPlanDependency::RESOLVED,
            ),
        ];
        $metadata = $this->compiler->compile($module->moduleClass());

        foreach ($metadata->dependencies() as $dependency) {
            $name = $dependency;

            foreach ($this->registry->all() as $candidate) {
                if ($candidate->moduleClass() === $dependency) {
                    $name = $candidate->name().'='.$dependency;
                    break;
                }
            }

            $dependencies[] = new ExportPlanDependency(
                'module',
                $name,
                null,
                null,
                ExportPlanDependency::MANUAL,
            );
        }

        return $dependencies;
    }

    /**
     * @param list<ExportPlanFile> $files
     * @return list<string>
     */
    private function collisions(string $target, array $files): array
    {
        $absolute = $this->targetAbsolutePath($target);
        $seen = [];
        $collisions = [];

        foreach ($files as $file) {
            $destination = $file->destination();
            $key = DIRECTORY_SEPARATOR === '\\' ? strtolower($destination) : $destination;
            $path = $absolute.'/'.$destination;

            if (isset($seen[$key])) {
                $collisions[] = $destination.'=duplicate';
            }

            if (file_exists($path) || is_link($path)) {
                $collisions[] = $destination.'=exists';
            }

            $seen[$key] = true;
        }

        return $collisions;
    }

    private function layout(DiscoveredModule $module): string
    {
        $root = $this->moduleRoot($module);

        return $this->samePath($module->path(), $root.'/app/'.$module->name().'Module.php')
            ? 'nwidart'
            : 'standalone';
    }

    private function moduleRoot(DiscoveredModule $module): string
    {
        return $this->normalize(rtrim($this->configuration->path(), '/\\').'/'.$module->name());
    }

    private function targetAbsolutePath(string $target): string
    {
        return $this->normalize(rtrim($this->applicationBasePath, '/\\').'/'.$target);
    }

    private function relativeToBase(string $path): string
    {
        $base = rtrim($this->normalize($this->applicationBasePath), '/');

        return substr($this->normalize($path), strlen($base) + 1);
    }

    private function relative(string $root, string $path): string
    {
        return substr($this->normalize($path), strlen(rtrim($this->normalize($root), '/')) + 1);
    }

    private function samePath(string $left, string $right): bool
    {
        $leftReal = realpath($left);
        $rightReal = realpath($right);

        return $leftReal !== false && $rightReal !== false && $leftReal === $rightReal;
    }

    private function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        return strlen($path) > 1 ? rtrim($path, '/') : $path;
    }
}

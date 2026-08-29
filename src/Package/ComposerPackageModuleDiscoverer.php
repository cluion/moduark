<?php

declare(strict_types=1);

namespace Cluion\Moduark\Package;

use Cluion\Moduark\Exceptions\PackageModuleDiscoveryFailed;
use Composer\InstalledVersions;
use JsonException;
use ReflectionClass;

final readonly class ComposerPackageModuleDiscoverer
{
    public function __construct(private string $installedPackagesPath)
    {
    }

    public static function fromComposerRuntime(): self
    {
        $runtimePath = (new ReflectionClass(InstalledVersions::class))->getFileName();

        if (! is_string($runtimePath)) {
            throw PackageModuleDiscoveryFailed::runtimeManifestUnavailable();
        }

        return new self(dirname($runtimePath).'/installed.json');
    }

    public function discover(): PackageModuleCatalog
    {
        if (! is_file($this->installedPackagesPath)) {
            return new PackageModuleCatalog([]);
        }

        $contents = file_get_contents($this->installedPackagesPath);

        if ($contents === false) {
            throw PackageModuleDiscoveryFailed::unreadableManifest($this->installedPackagesPath);
        }

        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw PackageModuleDiscoveryFailed::invalidManifest($this->installedPackagesPath);
        }

        if (! is_array($payload)) {
            throw PackageModuleDiscoveryFailed::invalidManifest($this->installedPackagesPath);
        }

        $packages = array_is_list($payload) ? $payload : ($payload['packages'] ?? null);

        if (! is_array($packages) || ! array_is_list($packages)) {
            throw PackageModuleDiscoveryFailed::invalidManifest($this->installedPackagesPath);
        }

        $modules = [];

        foreach ($packages as $row) {
            if (! is_array($row)) {
                throw PackageModuleDiscoveryFailed::invalidManifest($this->installedPackagesPath);
            }

            $extra = $row['extra'] ?? [];

            if (! is_array($extra) || ! array_key_exists('moduark', $extra)) {
                continue;
            }

            $package = $row['name'] ?? null;
            $installPath = $row['install-path'] ?? null;
            $metadata = $extra['moduark'];

            if (! is_string($package)
                || ! is_string($installPath)
                || ! is_array($metadata)
                || ($metadata['schema_version'] ?? null) !== PackageModuleCatalog::SCHEMA_VERSION
                || ! is_array($metadata['modules'] ?? null)
                || ! array_is_list($metadata['modules'])
                || $metadata['modules'] === []) {
                throw PackageModuleDiscoveryFailed::invalidMetadata(
                    is_string($package) ? $package : '[unknown]',
                );
            }

            $installRoot = $this->installRoot($package, $installPath);

            foreach ($metadata['modules'] as $descriptor) {
                if (! is_array($descriptor)) {
                    throw PackageModuleDiscoveryFailed::invalidMetadata($package);
                }

                $modules[] = PackageModuleDescriptor::fromArray(
                    $package,
                    $installRoot,
                    $descriptor,
                );
            }
        }

        return new PackageModuleCatalog($modules);
    }

    private function installRoot(string $package, string $installPath): string
    {
        $normalized = str_replace('\\', '/', $installPath);
        $absolute = str_starts_with($normalized, '/')
            || preg_match('/\A[A-Za-z]:\//', $normalized) === 1;
        $candidate = $absolute
            ? $normalized
            : dirname($this->installedPackagesPath).'/'.$normalized;
        $resolved = realpath($candidate);

        if ($resolved === false || ! is_dir($resolved)) {
            throw PackageModuleDiscoveryFailed::invalidInstallPath($package, $installPath);
        }

        return $resolved;
    }
}

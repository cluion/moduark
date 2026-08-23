<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Exceptions\ResourceManifestFailed;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use Cluion\Moduark\Resources\ResourceDescriptor;
use Cluion\Moduark\Resources\ResourceDiscoverer;
use Cluion\Moduark\Resources\ResourceHandler;
use Cluion\Moduark\Resources\ResourceManifest;
use Cluion\Moduark\Resources\ResourceManifestBuilder;
use Cluion\Moduark\Resources\ResourcePhase;
use Cluion\Moduark\Resources\ResourcePlugin;
use Cluion\Moduark\Resources\ResourcePluginRegistry;
use Cluion\Moduark\Resources\ResourceRuntime;
use PHPUnit\Framework\TestCase;

final class ResourceManifestTest extends TestCase
{
    public function test_manifest_round_trips_as_deterministic_pure_data(): void
    {
        $manifest = new ResourceManifest(
            [ManifestAlphaModule::class, ManifestBetaModule::class],
            [
                new ResourceDescriptor(
                    ManifestBetaModule::class,
                    'probe',
                    'second',
                    '/modules/Beta/probe.php',
                    'beta',
                    ['zeta' => true, 'alpha' => ['value' => 1]],
                ),
                new ResourceDescriptor(
                    ManifestAlphaModule::class,
                    'probe',
                    'first',
                    '/modules/Alpha/probe.php',
                ),
            ],
        );

        $payload = $manifest->toArray();
        $restored = ResourceManifest::fromArray($payload);

        self::assertSame($payload, $restored->toArray());
        self::assertSame(
            [ManifestAlphaModule::class, ManifestBetaModule::class],
            $restored->moduleClasses(),
        );
        self::assertSame(
            ['first', 'second'],
            array_map(
                static fn (ResourceDescriptor $resource): string => $resource->identity(),
                $restored->all(),
            ),
        );
        self::assertSame(
            ['alpha', 'zeta'],
            array_keys($restored->all()[1]->attributes()),
        );

        array_walk_recursive($payload, static function (mixed $value): void {
            self::assertTrue(is_scalar($value) || $value === null);
        });
    }

    public function test_manifest_rejects_duplicate_and_inactive_resource_identities(): void
    {
        $this->expectException(ResourceManifestFailed::class);
        $this->expectExceptionMessage('contains duplicate [probe] resource [SAME]');

        new ResourceManifest(
            [ManifestAlphaModule::class],
            [
                new ResourceDescriptor(ManifestAlphaModule::class, 'probe', 'same'),
                new ResourceDescriptor(ManifestAlphaModule::class, 'probe', 'SAME'),
            ],
        );
    }

    public function test_manifest_reports_cross_module_collision_keys(): void
    {
        $manifest = new ResourceManifest(
            [ManifestAlphaModule::class, ManifestBetaModule::class],
            [
                new ResourceDescriptor(
                    ManifestAlphaModule::class,
                    'config',
                    'alpha',
                    collisionKey: 'shared',
                ),
                new ResourceDescriptor(
                    ManifestBetaModule::class,
                    'config',
                    'beta',
                    collisionKey: 'shared',
                ),
            ],
        );

        self::assertSame('config', $manifest->collisions()[0]['plugin']);
        self::assertSame('shared', $manifest->collisions()[0]['collision_key']);
        self::assertCount(2, $manifest->collisions()[0]['resources']);
    }

    public function test_builder_uses_the_registry_and_dependency_ordered_module_set(): void
    {
        $plugins = new ResourcePluginRegistry;
        $plugins->register(new ResourcePlugin('probe', new ManifestProbeDiscoverer, new ManifestProbeHandler));
        $registry = new ModuleRegistry([
            $this->discovered('Alpha', ManifestAlphaModule::class),
            $this->discovered('Beta', ManifestBetaModule::class),
        ]);

        $manifest = (new ResourceManifestBuilder($plugins))->build($registry, [
            new ModuleDescriptor(ManifestBetaModule::class, [], []),
            new ModuleDescriptor(ManifestAlphaModule::class, [], []),
        ]);

        self::assertSame(
            [ManifestBetaModule::class, ManifestAlphaModule::class],
            $manifest->moduleClasses(),
        );
        self::assertSame(
            [ManifestBetaModule::class, ManifestAlphaModule::class],
            array_map(
                static fn (ResourceDescriptor $resource): string => $resource->moduleClass(),
                $manifest->all(),
            ),
        );
    }

    public function test_builder_rejects_unregistered_and_unserializable_module_configuration(): void
    {
        $registry = new ModuleRegistry([
            $this->discovered('Unknown', ManifestUnknownPluginModule::class),
        ]);

        $this->expectException(ResourceManifestFailed::class);
        $this->expectExceptionMessage('configures unknown resource plugin [unknown]');

        (new ResourceManifestBuilder(new ResourcePluginRegistry))->build($registry, [
            new ModuleDescriptor(ManifestUnknownPluginModule::class, [], []),
        ]);
    }

    public function test_builder_rejects_objects_before_plugin_discovery(): void
    {
        $registry = new ModuleRegistry([
            $this->discovered('Invalid', ManifestInvalidDataModule::class),
        ]);

        $this->expectException(ResourceManifestFailed::class);
        $this->expectExceptionMessage('must contain only scalar, null, and nested array values');

        (new ResourceManifestBuilder(new ResourcePluginRegistry))->build($registry, [
            new ModuleDescriptor(ManifestInvalidDataModule::class, [], []),
        ]);
    }

    /** @param class-string<Module> $moduleClass */
    private function discovered(string $name, string $moduleClass): DiscoveredModule
    {
        return new DiscoveredModule(
            $name,
            $moduleClass,
            '/modules/'.$name.'/'.$name.'Module.php',
            __NAMESPACE__,
        );
    }
}

final class ManifestProbeDiscoverer implements ResourceDiscoverer
{
    public function discover(
        DiscoveredModule $module,
        ModuleDescriptor $metadata,
        array $moduleConfiguration,
    ): array {
        if (! array_key_exists('probe', $moduleConfiguration)) {
            return [];
        }

        return [new ResourceDescriptor(
            $metadata->moduleClass(),
            'probe',
            strtolower($module->name()),
            dirname($module->path()).'/probe.php',
        )];
    }
}

final class ManifestProbeHandler implements ResourceHandler
{
    public function phase(): ResourcePhase
    {
        return ResourcePhase::Boot;
    }

    public function handle(ResourceDescriptor $resource, ResourceRuntime $runtime): void
    {
    }
}

final class ManifestAlphaModule extends Module
{
    public function resources(): array
    {
        return ['probe' => ['enabled' => true]];
    }
}

final class ManifestBetaModule extends Module
{
    public function resources(): array
    {
        return ['probe' => ['enabled' => true]];
    }
}

final class ManifestUnknownPluginModule extends Module
{
    public function resources(): array
    {
        return ['unknown' => true];
    }
}

final class ManifestInvalidDataModule extends Module
{
    public function resources(): array
    {
        return ['probe' => new \stdClass];
    }
}

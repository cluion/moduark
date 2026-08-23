<?php

declare(strict_types=1);

namespace Cluion\Moduark\Resources;

use Cluion\Moduark\Exceptions\ResourceManifestFailed;
use Cluion\Moduark\Module;

final readonly class ResourceManifest
{
    public const SCHEMA_VERSION = 1;

    /** @var list<class-string<Module>> */
    private array $moduleClasses;

    /** @var list<ResourceDescriptor> */
    private array $resources;

    /**
     * @param list<class-string<Module>> $moduleClasses
     * @param list<ResourceDescriptor> $resources
     */
    public function __construct(array $moduleClasses, array $resources)
    {
        if (count($moduleClasses) !== count(array_unique($moduleClasses))) {
            throw ResourceManifestFailed::moduleSetMismatch();
        }

        $moduleOrder = array_flip($moduleClasses);
        $seen = [];

        foreach ($resources as $resource) {
            if (! isset($moduleOrder[$resource->moduleClass()])) {
                throw ResourceManifestFailed::inactiveResource($resource->moduleClass());
            }

            $key = strtolower($resource->moduleClass().'|'.$resource->plugin().'|'.$resource->identity());

            if (isset($seen[$key])) {
                throw ResourceManifestFailed::duplicateResource(
                    $resource->moduleClass(),
                    $resource->plugin(),
                    $resource->identity(),
                );
            }

            $seen[$key] = true;
        }

        usort($resources, static function (ResourceDescriptor $left, ResourceDescriptor $right) use ($moduleOrder): int {
            $moduleComparison = $moduleOrder[$left->moduleClass()] <=> $moduleOrder[$right->moduleClass()];

            if ($moduleComparison !== 0) {
                return $moduleComparison;
            }

            $pluginComparison = strcmp($left->plugin(), $right->plugin());

            return $pluginComparison !== 0
                ? $pluginComparison
                : strcmp($left->identity(), $right->identity());
        });

        $this->moduleClasses = $moduleClasses;
        $this->resources = $resources;
    }

    /** @param array<mixed> $payload */
    public static function fromArray(array $payload): self
    {
        if (($payload['schema_version'] ?? null) !== self::SCHEMA_VERSION
            || ! self::isStringList($payload['modules'] ?? null)
            || ! is_array($payload['resources'] ?? null)) {
            throw ResourceManifestFailed::invalidPayload();
        }

        $resources = [];

        foreach ($payload['resources'] as $resource) {
            if (! is_array($resource)) {
                throw ResourceManifestFailed::invalidPayload();
            }

            $resources[] = ResourceDescriptor::fromArray($resource);
        }

        /** @var list<class-string<Module>> $modules */
        $modules = $payload['modules'];

        return new self($modules, $resources);
    }

    /** @return list<class-string<Module>> */
    public function moduleClasses(): array
    {
        return $this->moduleClasses;
    }

    /** @return list<ResourceDescriptor> */
    public function all(): array
    {
        return $this->resources;
    }

    /** @return list<ResourceDescriptor> */
    public function forModule(string $moduleClass): array
    {
        return array_values(array_filter(
            $this->resources,
            static fn (ResourceDescriptor $resource): bool => $resource->moduleClass() === $moduleClass,
        ));
    }

    /**
     * @return list<array{plugin: string, collision_key: string, resources: list<array<string, mixed>>}>
     */
    public function collisions(): array
    {
        $groups = [];

        foreach ($this->resources as $resource) {
            if ($resource->collisionKey() === null) {
                continue;
            }

            $key = strtolower($resource->plugin().'|'.$resource->collisionKey());
            $groups[$key][] = $resource;
        }

        $collisions = [];

        foreach ($groups as $resources) {
            if (count($resources) < 2) {
                continue;
            }

            $first = $resources[0];
            $collisionKey = $first->collisionKey();

            if ($collisionKey === null) {
                continue;
            }

            $collisions[] = [
                'plugin' => $first->plugin(),
                'collision_key' => $collisionKey,
                'resources' => array_map(
                    static fn (ResourceDescriptor $resource): array => $resource->toArray(),
                    $resources,
                ),
            ];
        }

        return $collisions;
    }

    /**
     * @return array{
     *     schema_version: int,
     *     modules: list<class-string<Module>>,
     *     resources: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'modules' => $this->moduleClasses,
            'resources' => array_map(
                static fn (ResourceDescriptor $resource): array => $resource->toArray(),
                $this->resources,
            ),
        ];
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

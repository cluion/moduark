<?php

declare(strict_types=1);

namespace Cluion\Moduark\Resources;

use Cluion\Moduark\Exceptions\ResourceManifestFailed;

final class ResourcePluginRegistry
{
    /** @var array<string, ResourcePlugin> */
    private array $plugins = [];

    public function register(ResourcePlugin $plugin): void
    {
        $id = $plugin->id();

        if (isset($this->plugins[$id])) {
            throw ResourceManifestFailed::duplicatePlugin($id);
        }

        $this->plugins[$id] = $plugin;
        ksort($this->plugins, SORT_STRING);
    }

    public function has(string $id): bool
    {
        return isset($this->plugins[$id]);
    }

    public function get(string $id): ResourcePlugin
    {
        if (! isset($this->plugins[$id])) {
            throw ResourceManifestFailed::invalidIdentity('plugin', $id);
        }

        return $this->plugins[$id];
    }

    /** @return list<ResourcePlugin> */
    public function all(): array
    {
        return array_values($this->plugins);
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_keys($this->plugins);
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Resources;

use Cluion\Moduark\Exceptions\ResourceManifestFailed;
use Cluion\Moduark\Module;

final readonly class ResourceDescriptor
{
    /**
     * @param class-string<Module> $moduleClass
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        private string $moduleClass,
        private string $plugin,
        private string $identity,
        private ?string $sourcePath = null,
        private ?string $runtimeNamespace = null,
        array $attributes = [],
        private ?string $collisionKey = null,
    ) {
        self::assertIdentity('Module class', $this->moduleClass);
        self::assertPlugin($this->plugin);
        self::assertIdentity('identity', $this->identity);

        if ($this->sourcePath !== null) {
            self::assertIdentity('source path', $this->sourcePath);
        }

        if ($this->runtimeNamespace !== null) {
            self::assertIdentity('runtime namespace', $this->runtimeNamespace);
        }

        if ($this->collisionKey !== null) {
            self::assertIdentity('collision key', $this->collisionKey);
        }

        $this->attributes = ResourceData::normalizeMap(
            $attributes,
            $this->plugin.'.'.$this->identity,
        );
    }

    /** @var array<string, mixed> */
    private array $attributes;

    /**
     * @param array<mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $module = $payload['module'] ?? null;
        $plugin = $payload['plugin'] ?? null;
        $identity = $payload['identity'] ?? null;
        $source = $payload['source'] ?? null;
        $namespace = $payload['namespace'] ?? null;
        $attributes = $payload['attributes'] ?? null;
        $collisionKey = $payload['collision_key'] ?? null;

        if (! is_string($module) || ! is_string($plugin) || ! is_string($identity)
            || ($source !== null && ! is_string($source))
            || ($namespace !== null && ! is_string($namespace))
            || ! is_array($attributes)
            || ($collisionKey !== null && ! is_string($collisionKey))) {
            throw ResourceManifestFailed::invalidPayload();
        }

        /** @var class-string<Module> $module */
        return new self(
            $module,
            $plugin,
            $identity,
            $source,
            $namespace,
            ResourceData::normalizeMap($attributes, $plugin.'.'.$identity),
            $collisionKey,
        );
    }

    /** @return class-string<Module> */
    public function moduleClass(): string
    {
        return $this->moduleClass;
    }

    public function plugin(): string
    {
        return $this->plugin;
    }

    public function identity(): string
    {
        return $this->identity;
    }

    public function sourcePath(): ?string
    {
        return $this->sourcePath;
    }

    public function runtimeNamespace(): ?string
    {
        return $this->runtimeNamespace;
    }

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function collisionKey(): ?string
    {
        return $this->collisionKey;
    }

    /**
     * @return array{
     *     module: class-string<Module>,
     *     plugin: string,
     *     identity: string,
     *     source: ?string,
     *     namespace: ?string,
     *     attributes: array<string, mixed>,
     *     collision_key: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'module' => $this->moduleClass,
            'plugin' => $this->plugin,
            'identity' => $this->identity,
            'source' => $this->sourcePath,
            'namespace' => $this->runtimeNamespace,
            'attributes' => $this->attributes,
            'collision_key' => $this->collisionKey,
        ];
    }

    private static function assertPlugin(string $plugin): void
    {
        if (preg_match('/\A[a-z][a-z0-9.-]*\z/', $plugin) !== 1) {
            throw ResourceManifestFailed::invalidIdentity('plugin', $plugin);
        }
    }

    private static function assertIdentity(string $kind, string $identity): void
    {
        if ($identity === '' || trim($identity) !== $identity) {
            throw ResourceManifestFailed::invalidIdentity($kind, $identity);
        }
    }
}

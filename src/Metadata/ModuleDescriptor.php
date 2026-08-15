<?php

declare(strict_types=1);

namespace Cluion\Moduark\Metadata;

use Cluion\Moduark\Capability;
use Cluion\Moduark\CapabilityRequirement;
use Cluion\Moduark\Module;
use Illuminate\Support\ServiceProvider;

final readonly class ModuleDescriptor
{
    /**
     * @param class-string<Module> $moduleClass
     * @param list<class-string<Module>> $dependencies
     * @param list<class-string<ServiceProvider>> $providers
     * @param list<CapabilityRequirement> $requirements
     * @param list<class-string<Capability>> $capabilities
     */
    public function __construct(
        private string $moduleClass,
        private array $dependencies,
        private array $providers,
        private array $requirements = [],
        private array $capabilities = [],
    ) {
    }

    /**
     * @param array{
     *     module: class-string<Module>,
     *     dependencies: list<class-string<Module>>,
     *     providers: list<class-string<ServiceProvider>>,
     *     requires?: list<array{
     *         capability: class-string<Capability>,
     *         port: class-string,
     *         adapter: class-string
     *     }>,
     *     provides?: list<class-string<Capability>>
     * } $values
     */
    public static function fromArray(array $values): self
    {
        return new self(
            $values['module'],
            $values['dependencies'],
            $values['providers'],
            array_map(
                static fn (array $requirement): CapabilityRequirement => CapabilityRequirement::fromArray($requirement),
                $values['requires'] ?? [],
            ),
            $values['provides'] ?? [],
        );
    }

    /**
     * @return class-string<Module>
     */
    public function moduleClass(): string
    {
        return $this->moduleClass;
    }

    /**
     * @return list<class-string<Module>>
     */
    public function dependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * @return list<class-string<ServiceProvider>>
     */
    public function providers(): array
    {
        return $this->providers;
    }

    /**
     * @return list<CapabilityRequirement>
     */
    public function requires(): array
    {
        return $this->requirements;
    }

    /**
     * @return list<class-string<Capability>>
     */
    public function provides(): array
    {
        return $this->capabilities;
    }

    /**
     * @return array{
     *     module: class-string<Module>,
     *     dependencies: list<class-string<Module>>,
     *     providers: list<class-string<ServiceProvider>>,
     *     requires: list<array{
     *         capability: class-string<Capability>,
     *         port: class-string,
     *         adapter: class-string
     *     }>,
     *     provides: list<class-string<Capability>>
     * }
     */
    public function toArray(): array
    {
        return [
            'module' => $this->moduleClass,
            'dependencies' => $this->dependencies,
            'providers' => $this->providers,
            'requires' => array_map(
                static fn (CapabilityRequirement $requirement): array => $requirement->toArray(),
                $this->requirements,
            ),
            'provides' => $this->capabilities,
        ];
    }
}

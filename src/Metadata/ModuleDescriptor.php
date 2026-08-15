<?php

declare(strict_types=1);

namespace Cluion\Moduark\Metadata;

use Cluion\Moduark\Module;
use Illuminate\Support\ServiceProvider;

final readonly class ModuleDescriptor
{
    /**
     * @param class-string<Module> $moduleClass
     * @param list<class-string<Module>> $dependencies
     * @param list<class-string<ServiceProvider>> $providers
     */
    public function __construct(
        private string $moduleClass,
        private array $dependencies,
        private array $providers,
    ) {
    }

    /**
     * @param array{
     *     module: class-string<Module>,
     *     dependencies: list<class-string<Module>>,
     *     providers: list<class-string<ServiceProvider>>
     * } $values
     */
    public static function fromArray(array $values): self
    {
        return new self(
            $values['module'],
            $values['dependencies'],
            $values['providers'],
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
     * @return array{
     *     module: class-string<Module>,
     *     dependencies: list<class-string<Module>>,
     *     providers: list<class-string<ServiceProvider>>
     * }
     */
    public function toArray(): array
    {
        return [
            'module' => $this->moduleClass,
            'dependencies' => $this->dependencies,
            'providers' => $this->providers,
        ];
    }
}

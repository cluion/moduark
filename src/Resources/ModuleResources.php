<?php

declare(strict_types=1);

namespace Cluion\Moduark\Resources;

use Illuminate\Console\Command;

final readonly class ModuleResources
{
    /**
     * @param list<string> $routePaths
     * @param list<class-string<Command>> $commands
     */
    public function __construct(
        private string $namespace,
        private array $routePaths,
        private ?string $viewPath,
        private ?string $translationPath,
        private ?string $migrationPath,
        private array $commands,
    ) {
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    /**
     * @return list<string>
     */
    public function routePaths(): array
    {
        return $this->routePaths;
    }

    public function viewPath(): ?string
    {
        return $this->viewPath;
    }

    public function translationPath(): ?string
    {
        return $this->translationPath;
    }

    public function migrationPath(): ?string
    {
        return $this->migrationPath;
    }

    /**
     * @return list<class-string<Command>>
     */
    public function commands(): array
    {
        return $this->commands;
    }
}

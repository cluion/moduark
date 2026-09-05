<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use Symfony\Component\Console\Command\Command;

final class NativeGeneratorBridgeState
{
    /** @var array<string, NativeGeneratorBridgeDecoratedCommand> */
    private array $decorated = [];

    /** @var array<string, Command> */
    private array $preparedOriginals = [];

    /** @var array<string, NativeGeneratorBridgeDecoratedCommand> */
    private array $preparedDecorated = [];

    private ?string $registrationFailure = null;

    /** @param array<string, NativeGeneratorBridgeDecoratedCommand> $decorated */
    public function activate(array $decorated): void
    {
        $this->decorated = $decorated;
        $this->registrationFailure = null;
    }

    /**
     * @param array<string, Command> $originals
     * @param array<string, NativeGeneratorBridgeDecoratedCommand> $decorated
     */
    public function prepare(array $originals, array $decorated): void
    {
        $this->preparedOriginals = $originals;
        $this->preparedDecorated = $decorated;
    }

    /** @return array<string, Command> */
    public function preparedOriginals(): array
    {
        return $this->preparedOriginals;
    }

    /** @return array<string, NativeGeneratorBridgeDecoratedCommand> */
    public function preparedDecorated(): array
    {
        return $this->preparedDecorated;
    }

    public function discardPreparation(): void
    {
        $this->preparedOriginals = [];
        $this->preparedDecorated = [];
    }

    public function fail(string $message): void
    {
        $this->decorated = [];
        $this->registrationFailure = $message;
    }

    public function active(): bool
    {
        return $this->decorated !== [];
    }

    public function owns(string $name, Command $command): bool
    {
        return ($this->decorated[$name] ?? null) === $command;
    }

    public function registrationFailure(): ?string
    {
        return $this->registrationFailure;
    }
}

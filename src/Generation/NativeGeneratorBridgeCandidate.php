<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use Symfony\Component\Console\Command\Command;

final readonly class NativeGeneratorBridgeCandidate
{
    /**
     * @param class-string<Command> $expectedClass
     * @param class-string<Command>|null $actualClass
     * @param list<NativeGeneratorBridgeDiagnostic> $diagnostics
     */
    public function __construct(
        private string $command,
        private string $generatorId,
        private string $expectedClass,
        private ?string $actualClass,
        private array $diagnostics,
    ) {
    }

    public function command(): string
    {
        return $this->command;
    }

    public function generatorId(): string
    {
        return $this->generatorId;
    }

    public function ready(): bool
    {
        return $this->diagnostics === [];
    }

    /** @return list<NativeGeneratorBridgeDiagnostic> */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    /**
     * @return array{
     *     command: string,
     *     generator_id: string,
     *     expected_class: class-string<Command>,
     *     actual_class: class-string<Command>|null,
     *     status: 'ready'|'blocked',
     *     diagnostics: list<array{code: string, message: string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'command' => $this->command,
            'generator_id' => $this->generatorId,
            'expected_class' => $this->expectedClass,
            'actual_class' => $this->actualClass,
            'status' => $this->ready() ? 'ready' : 'blocked',
            'diagnostics' => array_map(
                static fn (NativeGeneratorBridgeDiagnostic $diagnostic): array => $diagnostic->toArray(),
                $this->diagnostics,
            ),
        ];
    }
}

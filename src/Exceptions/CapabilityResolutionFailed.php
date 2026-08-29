<?php

declare(strict_types=1);

namespace Cluion\Moduark\Exceptions;

use Cluion\Moduark\Capability;
use Cluion\Moduark\Capabilities\CapabilityResolutionReason;
use Cluion\Moduark\Module;
use LogicException;
use RuntimeException;

final class CapabilityResolutionFailed extends RuntimeException
{
    private ?CapabilityResolutionReason $resolutionReason = null;

    /** @var array<string, string|list<string>> */
    private array $resolutionContext = [];

    /**
     * @param class-string<Capability> $capability
     * @param class-string<Module> $consumer
     */
    public static function missingProvider(string $capability, string $consumer): self
    {
        return self::withReason(
            "Capability [{$capability}] required by [{$consumer}] has no provider.",
            CapabilityResolutionReason::MissingProvider,
            ['capability' => $capability, 'consumer' => $consumer],
        );
    }

    /**
     * @param class-string<Capability> $capability
     * @param class-string<Module> $consumer
     * @param list<class-string<Module>> $providers
     */
    public static function ambiguousProvider(
        string $capability,
        string $consumer,
        array $providers,
    ): self {
        return self::withReason(
            sprintf(
                'Capability [%s] required by [%s] has multiple providers [%s].',
                $capability,
                $consumer,
                implode(', ', $providers),
            ),
            CapabilityResolutionReason::AmbiguousProvider,
            [
                'capability' => $capability,
                'consumer' => $consumer,
                'providers' => $providers,
            ],
        );
    }

    /**
     * @param class-string $port
     * @param class-string<Module> $firstConsumer
     * @param class-string<Module> $secondConsumer
     */
    public static function duplicatePort(
        string $port,
        string $firstConsumer,
        string $secondConsumer,
    ): self {
        $consumers = [$firstConsumer, $secondConsumer];
        sort($consumers, SORT_STRING);

        return self::withReason(
            sprintf(
                'Capability Port [%s] is required by multiple consumer Modules [%s].',
                $port,
                implode(', ', $consumers),
            ),
            CapabilityResolutionReason::DuplicatePort,
            [
                'port' => $port,
                'consumers' => $consumers,
            ],
        );
    }

    public function reason(): CapabilityResolutionReason
    {
        return $this->resolutionReason
            ?? throw new LogicException('Capability resolution reason is unavailable.');
    }

    /** @return array<string, string|list<string>> */
    public function context(): array
    {
        return $this->resolutionContext;
    }

    /**
     * @param array<string, string|list<string>> $context
     */
    private static function withReason(
        string $message,
        CapabilityResolutionReason $reason,
        array $context,
    ): self {
        $exception = new self($message);
        $exception->resolutionReason = $reason;
        $exception->resolutionContext = $context;

        return $exception;
    }
}

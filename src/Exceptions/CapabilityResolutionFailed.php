<?php

declare(strict_types=1);

namespace Cluion\Moduark\Exceptions;

use Cluion\Moduark\Capability;
use Cluion\Moduark\Module;
use RuntimeException;

final class CapabilityResolutionFailed extends RuntimeException
{
    /**
     * @param class-string<Capability> $capability
     * @param class-string<Module> $consumer
     */
    public static function missingProvider(string $capability, string $consumer): self
    {
        return new self("Capability [{$capability}] required by [{$consumer}] has no provider.");
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
        return new self(sprintf(
            'Capability [%s] required by [%s] has multiple providers [%s].',
            $capability,
            $consumer,
            implode(', ', $providers),
        ));
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

        return new self(sprintf(
            'Capability Port [%s] is required by multiple consumer Modules [%s].',
            $port,
            implode(', ', $consumers),
        ));
    }
}

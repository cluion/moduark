<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use RuntimeException;
use Illuminate\Console\Application as Artisan;
use ReflectionProperty;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;
use Throwable;

final readonly class NativeGeneratorBridgeCommandSet
{
    /** @param array<string, Command> $commands */
    public function restore(SymfonyApplication $application, array $commands): void
    {
        foreach ($commands as $command) {
            $application->addCommand($command);
        }

        foreach ($commands as $name => $command) {
            if ($application->get($name) !== $command) {
                throw new RuntimeException("Native command [{$name}] was not restored.");
            }
        }
    }

    /**
     * @param array<string, Command> $originals
     * @param array<string, NativeGeneratorBridgeDecoratedCommand> $decorated
     */
    public function replace(
        SymfonyApplication $application,
        array $originals,
        array $decorated,
    ): ?string {
        try {
            foreach ($decorated as $command) {
                $application->addCommand($command);
            }

            foreach ($decorated as $name => $command) {
                if ($application->get($name) !== $command) {
                    throw new RuntimeException("Native command [{$name}] was not decorated.");
                }
            }
        } catch (Throwable $exception) {
            $rollbackFailures = [];

            foreach ($originals as $name => $original) {
                try {
                    $application->addCommand($original);
                } catch (Throwable) {
                    $rollbackFailures[] = $name;
                }
            }

            $message = $exception->getMessage();

            if ($rollbackFailures !== []) {
                $message .= sprintf(
                    ' Rollback failed for [%s].',
                    implode(', ', $rollbackFailures),
                );

                throw new RuntimeException($message, previous: $exception);
            }

            return $message;
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function ownerMap(Artisan $application): array
    {
        $owners = (new ReflectionProperty(Artisan::class, 'commandMap'))->getValue($application);

        if (! is_array($owners)) {
            throw new RuntimeException('Laravel native command owner map is unavailable.');
        }

        $normalized = [];

        foreach ($owners as $name => $owner) {
            if (! is_string($name)) {
                throw new RuntimeException('Laravel native command owner map contains a non-string name.');
            }

            $normalized[$name] = $owner;
        }

        return $normalized;
    }
}

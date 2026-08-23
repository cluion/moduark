<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Providers\ArtisanServiceProvider;
use Illuminate\Database\MigrationServiceProvider;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

final class LaravelMakerInventory
{
    public const SCHEMA = 1;

    /**
     * @return array{
     *     schema: int,
     *     laravel_major: int,
     *     commands: array<string, array{
     *         class: class-string,
     *         aliases: list<string>,
     *         arguments: list<string>,
     *         options: list<string>
     *     }>
     * }
     */
    public static function capture(Application $application): array
    {
        $major = self::frameworkMajor();
        $commands = [];

        foreach ([
            new ArtisanServiceProvider($application),
            new MigrationServiceProvider($application),
        ] as $provider) {
            $provider->register();

            foreach ($provider->provides() as $commandClass) {
                if (! is_string($commandClass)) {
                    throw new RuntimeException('Laravel exposed an invalid console service class.');
                }

                $command = $application->make($commandClass);

                if (! $command instanceof Command) {
                    continue;
                }

                $name = $command->getName();

                if (! is_string($name) || ! str_starts_with($name, 'make:')) {
                    continue;
                }

                $commands[$name] = self::command($command);
            }
        }

        ksort($commands, SORT_STRING);

        return [
            'schema' => self::SCHEMA,
            'laravel_major' => $major,
            'commands' => $commands,
        ];
    }

    private static function frameworkMajor(): int
    {
        if (preg_match('/\A([0-9]+)\./', Application::VERSION, $match) !== 1) {
            throw new RuntimeException('Unable to determine the Laravel framework major version.');
        }

        return (int) $match[1];
    }

    /**
     * @return array{
     *     class: class-string,
     *     aliases: list<string>,
     *     arguments: list<string>,
     *     options: list<string>
     * }
     */
    private static function command(Command $command): array
    {
        $aliases = [];

        foreach ($command->getAliases() as $alias) {
            if (! is_string($alias)) {
                throw new RuntimeException('Laravel exposed a non-string console command alias.');
            }

            $aliases[] = $alias;
        }

        $arguments = array_map(self::argument(...), $command->getNativeDefinition()->getArguments());
        $options = array_map(self::option(...), $command->getNativeDefinition()->getOptions());

        sort($aliases, SORT_STRING);
        sort($arguments, SORT_STRING);
        sort($options, SORT_STRING);

        return [
            'class' => $command::class,
            'aliases' => $aliases,
            'arguments' => $arguments,
            'options' => $options,
        ];
    }

    private static function argument(InputArgument $argument): string
    {
        return implode('|', [
            $argument->getName(),
            $argument->isRequired() ? 'required' : 'optional',
            $argument->isArray() ? 'multiple' : 'single',
        ]);
    }

    private static function option(InputOption $option): string
    {
        $shortcut = $option->getShortcut();
        $shortcuts = $shortcut === null || $shortcut === '' ? [] : explode('|', $shortcut);

        sort($shortcuts, SORT_STRING);

        return implode('|', [
            $option->getName(),
            implode(',', $shortcuts),
            match (true) {
                $option->isNegatable() => 'negatable',
                ! $option->acceptValue() => 'flag',
                $option->isValueRequired() => 'required-value',
                default => 'optional-value',
            },
            $option->isArray() ? 'multiple' : 'single',
        ]);
    }
}

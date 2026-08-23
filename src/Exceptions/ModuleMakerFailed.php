<?php

declare(strict_types=1);

namespace Cluion\Moduark\Exceptions;

use RuntimeException;

final class ModuleMakerFailed extends RuntimeException
{
    public static function unknownModule(string $module): self
    {
        return new self("Module [{$module}] was not found.");
    }

    public static function unsupportedType(string $type): self
    {
        return new self(
            "Maker type [{$type}] is not supported; expected cast, channel, class, component, controller, enum, event, exception, factory, interface, job, job-middleware, listener, mail, middleware, migration, model, notification, observer, policy, request, resource, rule, scope, seeder, trait, or view.",
        );
    }

    public static function invalidName(string $name): self
    {
        return new self(
            "Maker name [{$name}] must contain one or more StudlyCase class segments.",
        );
    }

    public static function reservedName(string $name): self
    {
        return new self("Maker class name [{$name}] is reserved by PHP.");
    }

    public static function applicationPathUnavailable(): self
    {
        return new self('The Laravel application source path is unavailable.');
    }

    public static function applicationNamespaceUnavailable(): self
    {
        return new self('The Laravel application namespace could not be resolved.');
    }

    public static function externalModulePath(string $module, string $path, string $applicationPath): self
    {
        return new self(
            "Module [{$module}] path [{$path}] must be inside Laravel application path [{$applicationPath}] for moduark:make.",
        );
    }

    public static function namespaceMismatch(string $module, string $namespace, string $expected): self
    {
        return new self(
            "Module [{$module}] namespace [{$namespace}] must match application path namespace [{$expected}] for moduark:make.",
        );
    }

    public static function unsupportedOption(string $option, string $type): self
    {
        return new self("The --{$option} option is not supported for Maker type [{$type}].");
    }

    public static function invalidOptionValue(string $option): self
    {
        return new self("The --{$option} option must be a non-empty string when provided.");
    }

    public static function invalidPolicyModel(string $model): self
    {
        return new self(
            "Policy model [{$model}] must contain one or more StudlyCase class segments relative to the Module Models namespace.",
        );
    }

    public static function invalidFactoryModel(string $model): self
    {
        return new self(
            "Factory model [{$model}] must contain one or more StudlyCase class segments relative to the Module Models namespace.",
        );
    }

    public static function invalidFactoryName(string $name): self
    {
        return new self(
            "Factory name [{$name}] must identify a model before the Factory suffix.",
        );
    }

    public static function invalidObserverModel(string $model): self
    {
        return new self(
            "Observer model [{$model}] must contain one or more StudlyCase class segments relative to the Module Models namespace.",
        );
    }

    public static function invalidListenerEvent(string $event): self
    {
        return new self(
            "Listener event [{$event}] must contain one or more StudlyCase class segments relative to the Module Events namespace.",
        );
    }

    public static function invalidMigrationName(string $name): self
    {
        return new self(
            "Migration name [{$name}] must be one StudlyCase segment without a namespace.",
        );
    }

    public static function invalidMigrationTable(string $table): self
    {
        return new self(
            "Migration table [{$table}] must be a lowercase snake_case database identifier.",
        );
    }

    public static function conflictingMigrationOptions(): self
    {
        return new self('The migration Maker options [--create, --table] cannot be combined.');
    }

    /** @param list<string> $options */
    public static function conflictingOptions(array $options): self
    {
        return new self(sprintf(
            'The controller Maker options [%s] cannot be combined.',
            implode(', ', array_map(static fn (string $option): string => '--'.$option, $options)),
        ));
    }

    /** @param list<string> $options */
    public static function conflictingResourceOptions(array $options): self
    {
        return new self(sprintf(
            'The resource Maker options [%s] cannot be combined.',
            implode(', ', array_map(static fn (string $option): string => '--'.$option, $options)),
        ));
    }

    public static function conflictingJobOptions(): self
    {
        return new self('The job Maker options [--sync, --batched] cannot be combined.');
    }

    /** @param list<string> $options */
    public static function conflictingComponentOptions(array $options): self
    {
        return new self(sprintf(
            'The component Maker options [%s] cannot be combined.',
            implode(', ', array_map(static fn (string $option): string => '--'.$option, $options)),
        ));
    }

    public static function invalidComponentPath(string $path): self
    {
        return new self(
            "Component path [{$path}] must contain one or more lowercase kebab-case directory segments.",
        );
    }

    public static function invalidViewName(string $name): self
    {
        return new self(
            "View name [{$name}] must contain one or more alphanumeric path segments separated by dots or slashes.",
        );
    }

    public static function invalidViewExtension(string $extension): self
    {
        return new self(
            "View extension [{$extension}] must contain one or more lowercase alphanumeric segments separated by dots.",
        );
    }

    /** @param list<string> $paths */
    public static function ambiguousMigration(string $name, array $paths): self
    {
        return new self(sprintf(
            'Migration [%s] has multiple Module targets: %s.',
            $name,
            implode(', ', $paths),
        ));
    }
}

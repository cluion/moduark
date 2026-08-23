<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use Cluion\Moduark\Exceptions\ModuleMakerFailed;

enum ModuleMakerType: string implements GeneratorDescriptor
{
    case Model = 'model';
    case Controller = 'controller';

    public static function parse(string $type): self
    {
        return match (strtolower($type)) {
            self::Model->value => self::Model,
            self::Controller->value => self::Controller,
            default => throw ModuleMakerFailed::unsupportedType($type),
        };
    }

    public function command(): string
    {
        return 'make:'.$this->value;
    }

    public function id(): string
    {
        return $this->value;
    }

    public function namespace(): string
    {
        return match ($this) {
            self::Model => 'Models',
            self::Controller => 'Http\\Controllers',
        };
    }

    public function targetNamespace(): string
    {
        return $this->namespace();
    }

    public function plan(ModuleMakerTarget $target, GenerationOptions $options): GenerationPlan
    {
        if ($this === self::Model) {
            foreach (
                [
                    'invokable' => $options->invokable,
                    'resource' => $options->resource,
                    'api' => $options->api,
                ] as $option => $enabled
            ) {
                if ($enabled) {
                    throw ModuleMakerFailed::unsupportedOption($option, $this->value);
                }
            }
        } elseif ($options->invokable && ($options->resource || $options->api)) {
            $conflicts = ['invokable'];

            if ($options->resource) {
                $conflicts[] = 'resource';
            }

            if ($options->api) {
                $conflicts[] = 'api';
            }

            throw ModuleMakerFailed::conflictingOptions($conflicts);
        }

        $parameters = [
            'name' => $target->className(),
            '--force' => $options->force,
        ];

        if ($this === self::Controller) {
            $parameters += [
                '--invokable' => $options->invokable,
                '--resource' => $options->resource,
                '--api' => $options->api,
            ];
        }

        $parameters['--no-interaction'] = true;

        return new GenerationPlan([
            new GenerationTarget(
                $this->id(),
                $this->command(),
                $target->className(),
                $target->filePath(),
                $target->moduleRelativePath(),
                $options->force,
                array_filter(
                    $parameters,
                    static fn (bool|string $value): bool => $value !== false,
                ),
            ),
        ]);
    }
}

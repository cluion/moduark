<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use Cluion\Moduark\Exceptions\ModuleMakerFailed;

enum ModuleMakerType: string
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

    public function namespace(): string
    {
        return match ($this) {
            self::Model => 'Models',
            self::Controller => 'Http\\Controllers',
        };
    }
}

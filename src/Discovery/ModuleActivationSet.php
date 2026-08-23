<?php

declare(strict_types=1);

namespace Cluion\Moduark\Discovery;

use InvalidArgumentException;

final readonly class ModuleActivationSet
{
    /** @var array<string, true>|null */
    private ?array $activeNames;

    /**
     * @param list<string>|null $activeNames
     */
    private function __construct(?array $activeNames)
    {
        if ($activeNames === null) {
            $this->activeNames = null;

            return;
        }

        $indexed = [];

        foreach ($activeNames as $name) {
            if ($name === '' || trim($name) !== $name) {
                throw new InvalidArgumentException(
                    'Active Module names must be non-empty strings without surrounding whitespace.',
                );
            }

            $indexed[$name] = true;
        }

        ksort($indexed, SORT_STRING);

        $this->activeNames = $indexed;
    }

    public static function all(): self
    {
        return new self(null);
    }

    /**
     * @param list<string> $activeNames
     */
    public static function only(array $activeNames): self
    {
        return new self($activeNames);
    }

    public function includes(string $moduleName): bool
    {
        return $this->activeNames === null || isset($this->activeNames[$moduleName]);
    }

    public function fingerprint(): string
    {
        if ($this->activeNames === null) {
            return 'all:v1';
        }

        return 'only:v1:'.hash(
            'sha256',
            json_encode(array_keys($this->activeNames), JSON_THROW_ON_ERROR),
        );
    }
}

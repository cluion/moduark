<?php

declare(strict_types=1);

namespace Cluion\Moduark\Configuration;

use Cluion\Moduark\Architecture\Level;
use InvalidArgumentException;

final readonly class ModulesConfig
{
    /**
     * @param array<string, mixed> $values
     */
    private function __construct(private array $values)
    {
    }

    /**
     * Laravel's mergeConfigFrom() only merges the first level. Moduark resolves
     * its nested architecture defaults explicitly so partial rule overrides do
     * not remove the selected level or other defaults.
     *
     * @param array<mixed> $defaults
     * @param array<mixed> $configured
     */
    public static function from(array $defaults, array $configured): self
    {
        $values = self::normalizeTopLevelKeys(array_replace_recursive($defaults, $configured));

        self::validate($values);

        return new self($values);
    }

    public function path(): string
    {
        /** @var string */
        return $this->values['path'];
    }

    public function level(): Level
    {
        /** @var array{level: int, rules: array<string, mixed>} $architecture */
        $architecture = $this->values['architecture'];

        return Level::from($architecture['level']);
    }

    public function activationPath(): string
    {
        $activation = $this->values['activation'] ?? null;

        if (! is_array($activation) || ! isset($activation['path']) || ! is_string($activation['path'])) {
            return dirname($this->path()).'/moduark-modules.json';
        }

        return $activation['path'];
    }

    public function baselinePath(): ?string
    {
        /** @var array{baseline?: string, level: int, rules: array<string, mixed>} $architecture */
        $architecture = $this->values['architecture'];

        return $architecture['baseline'] ?? null;
    }

    public function suppressionPath(): ?string
    {
        /** @var array{suppressions?: string, level: int, rules: array<string, mixed>} $architecture */
        $architecture = $this->values['architecture'];

        return $architecture['suppressions'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var array{level: int, rules: array<string, mixed>} $architecture */
        $architecture = $this->values['architecture'];

        return $architecture['rules'];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function validate(array $values): void
    {
        if (! isset($values['path']) || ! is_string($values['path']) || $values['path'] === '') {
            throw new InvalidArgumentException('The moduark.path configuration must be a non-empty string.');
        }

        if (! isset($values['architecture']) || ! is_array($values['architecture'])) {
            throw new InvalidArgumentException('The moduark.architecture configuration must be an array.');
        }

        if (array_key_exists('activation', $values) && ! is_array($values['activation'])) {
            throw new InvalidArgumentException('The moduark.activation configuration must be an array.');
        }

        $activationPath = is_array($values['activation'] ?? null)
            ? ($values['activation']['path'] ?? null)
            : null;

        if (array_key_exists('activation', $values)
            && (! is_string($activationPath) || trim($activationPath) === '')) {
            throw new InvalidArgumentException(
                'The moduark.activation.path configuration must be a non-empty string.',
            );
        }

        $architecture = $values['architecture'];
        $level = $architecture['level'] ?? null;

        if (! is_int($level) || $level < 0 || $level > 3) {
            throw new InvalidArgumentException('The moduark.architecture.level configuration must be an integer from 0 to 3.');
        }

        if (! isset($architecture['rules']) || ! is_array($architecture['rules'])) {
            throw new InvalidArgumentException('The moduark.architecture.rules configuration must be an array.');
        }

        if (
            array_key_exists('baseline', $architecture)
            && (! is_string($architecture['baseline']) || trim($architecture['baseline']) === '')
        ) {
            throw new InvalidArgumentException(
                'The moduark.architecture.baseline configuration must be a non-empty string.',
            );
        }

        if (
            array_key_exists('suppressions', $architecture)
            && (! is_string($architecture['suppressions']) || trim($architecture['suppressions']) === '')
        ) {
            throw new InvalidArgumentException(
                'The moduark.architecture.suppressions configuration must be a non-empty string.',
            );
        }
    }

    /**
     * @param array<mixed> $values
     * @return array<string, mixed>
     */
    private static function normalizeTopLevelKeys(array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('The moduark configuration must use string keys.');
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Export;

use Composer\Semver\VersionParser;
use InvalidArgumentException;
use UnexpectedValueException;

final readonly class ExportDependencyMapping
{
    private function __construct(
        private string $module,
        private string $package,
        private string $constraint,
        private string $namespace,
    ) {
    }

    public static function fromString(string $mapping): self
    {
        if (trim($mapping) !== $mapping || substr_count($mapping, '=>') !== 1) {
            throw self::invalid($mapping);
        }

        [$identity, $namespace] = explode('=>', $mapping, 2);

        if (substr_count($identity, '=') !== 1) {
            throw self::invalid($mapping);
        }

        [$module, $requirement] = explode('=', $identity, 2);
        [$package, $constraint] = array_pad(explode(':', $requirement, 2), 2, null);

        if ($module === ''
            || preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $module) !== 1
            || ! is_string($package)
            || $package === ''
            || preg_match('/\A[a-z0-9](?:[a-z0-9._-]*[a-z0-9])?\/[a-z0-9](?:[a-z0-9._-]*[a-z0-9])?\z/', $package) !== 1
            || ! is_string($constraint)
            || $constraint === ''
            || trim($constraint) !== $constraint
            || preg_match('/\A[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*\z/', $namespace) !== 1) {
            throw self::invalid($mapping);
        }

        try {
            (new VersionParser)->parseConstraints($constraint);
        } catch (UnexpectedValueException) {
            throw self::invalid($mapping);
        }

        return new self($module, $package, $constraint, $namespace);
    }

    public function module(): string
    {
        return $this->module;
    }

    public function package(): string
    {
        return $this->package;
    }

    public function constraint(): string
    {
        return $this->constraint;
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    public function toString(): string
    {
        return sprintf(
            '%s=%s:%s=>%s',
            $this->module,
            $this->package,
            $this->constraint,
            $this->namespace,
        );
    }

    private static function invalid(string $mapping): InvalidArgumentException
    {
        return new InvalidArgumentException(
            "Invalid export dependency mapping [{$mapping}]; expected Module=vendor/package:constraint=>Namespace.",
        );
    }
}

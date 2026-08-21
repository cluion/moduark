<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Suppression;

use Cluion\Moduark\Analysis\Baseline\PortablePath;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\Violation;
use InvalidArgumentException;

final readonly class SuppressionEntry
{
    private const FIELDS = [
        'rule',
        'code',
        'file',
        'line',
        'consumer',
        'target',
        'symbol',
        'reason',
    ];

    public function __construct(
        private RuleId $rule,
        private string $code,
        private ?string $file,
        private ?int $line,
        private ?string $consumer,
        private ?string $target,
        private ?string $symbol,
        private string $reason,
    ) {
        if (preg_match('/\AMOD-[A-Z][A-Z0-9-]*-[0-9]{3}\z/', $code) !== 1) {
            throw new InvalidArgumentException(
                "Suppression code [{$code}] must use the MOD-NAME-000 format.",
            );
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A suppression reason must be a non-empty string.');
        }

        if ($line !== null && ($line < 1 || $file === null)) {
            throw new InvalidArgumentException(
                'A suppression line must be positive and accompanied by a file.',
            );
        }

        if ($file === null && $symbol === null && ($consumer === null || $target === null)) {
            throw new InvalidArgumentException(
                'A suppression must select a file, symbol, or consumer and target pair; global ignores are not allowed.',
            );
        }

        if ($rule === RuleId::UndeclaredDependencies
            && $code === 'MOD-DEPENDENCY-002'
            && ($consumer === null || $target === null)) {
            throw new InvalidArgumentException(
                'An undeclared dependency suppression must select both consumer and target Modules.',
            );
        }
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        $unknown = array_values(array_diff(array_keys($values), self::FIELDS));

        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf(
                'Suppression contains unknown field%s: %s.',
                count($unknown) === 1 ? '' : 's',
                implode(', ', $unknown),
            ));
        }

        $ruleValue = self::requiredString($values, 'rule');
        $rule = RuleId::tryFrom($ruleValue);

        if ($rule === null) {
            throw new InvalidArgumentException("Suppression rule [{$ruleValue}] is not recognized.");
        }

        $file = self::optionalString($values, 'file');

        if ($file !== null) {
            $file = self::normalizeFile($file);
        }

        $line = $values['line'] ?? null;

        if ($line !== null && ! is_int($line)) {
            throw new InvalidArgumentException('A suppression line must be a positive integer.');
        }

        return new self(
            $rule,
            self::requiredString($values, 'code'),
            $file,
            $line,
            self::optionalString($values, 'consumer'),
            self::optionalString($values, 'target'),
            self::optionalString($values, 'symbol'),
            self::requiredString($values, 'reason'),
        );
    }

    public function rule(): RuleId
    {
        return $this->rule;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function scope(): string
    {
        $parts = [];

        if ($this->file !== null) {
            $parts[] = $this->file.($this->line === null ? '' : ':'.$this->line);
        }

        if ($this->consumer !== null || $this->target !== null) {
            $parts[] = ($this->consumer ?? '*').' -> '.($this->target ?? '*');
        }

        if ($this->symbol !== null) {
            $parts[] = $this->symbol;
        }

        return implode(' | ', $parts);
    }

    public function identity(): string
    {
        return json_encode($this->selectorArray(), JSON_THROW_ON_ERROR);
    }

    public function matches(Violation $violation, string $basePath): bool
    {
        if ($violation->rule() !== $this->rule || $violation->code() !== $this->code) {
            return false;
        }

        if (
            $this->file !== null
            && ($violation->file() === null
                || PortablePath::relative($violation->file(), $basePath) !== $this->file)
        ) {
            return false;
        }

        if ($this->line !== null && $violation->line() !== $this->line) {
            return false;
        }

        if ($this->consumer !== null && $violation->consumer() !== $this->consumer) {
            return false;
        }

        if ($this->target !== null && $violation->target() !== $this->target) {
            return false;
        }

        return $this->symbol === null || $violation->symbol() === $this->symbol;
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return array_filter(
            $this->selectorArray() + ['reason' => $this->reason],
            static fn (mixed $value): bool => $value !== null,
        );
    }

    /**
     * @return array{rule: string, code: string, file: ?string, line: ?int, consumer: ?string, target: ?string, symbol: ?string}
     */
    private function selectorArray(): array
    {
        return [
            'rule' => $this->rule->value,
            'code' => $this->code,
            'file' => $this->file,
            'line' => $this->line,
            'consumer' => $this->consumer,
            'target' => $this->target,
            'symbol' => $this->symbol,
        ];
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function requiredString(array $values, string $field): string
    {
        $value = $values[$field] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("A suppression {$field} must be a non-empty string.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function optionalString(array $values, string $field): ?string
    {
        if (! array_key_exists($field, $values)) {
            return null;
        }

        $value = $values[$field];

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("A suppression {$field} must be a non-empty string.");
        }

        return $value;
    }

    private static function normalizeFile(string $file): string
    {
        $file = str_replace('\\', '/', $file);

        if (
            str_starts_with($file, '/')
            || preg_match('/\A[A-Za-z]:\//', $file) === 1
            || in_array('..', explode('/', $file), true)
        ) {
            throw new InvalidArgumentException(
                'A suppression file must be a repository-relative path without parent traversal.',
            );
        }

        $segments = array_values(array_filter(
            explode('/', $file),
            static fn (string $segment): bool => $segment !== '' && $segment !== '.',
        ));

        if ($segments === []) {
            throw new InvalidArgumentException('A suppression file must identify a repository-relative file.');
        }

        return implode('/', $segments);
    }
}

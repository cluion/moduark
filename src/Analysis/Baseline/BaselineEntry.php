<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Baseline;

use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Architecture\Violation;
use InvalidArgumentException;
use ValueError;

final readonly class BaselineEntry
{
    public function __construct(
        private RuleId $rule,
        private string $code,
        private Severity $severity,
        private ?string $file,
        private ?string $consumer,
        private ?string $target,
        private ?string $symbol,
        private int $count,
    ) {
        if (preg_match('/\AMOD-[A-Z][A-Z0-9-]*-[0-9]{3}\z/', $code) !== 1) {
            throw new InvalidArgumentException("Baseline diagnostic code [{$code}] must use the MOD-NAME-000 format.");
        }

        foreach (['file' => $file, 'consumer' => $consumer, 'target' => $target, 'symbol' => $symbol] as $name => $value) {
            if ($value !== null && trim($value) === '') {
                throw new InvalidArgumentException("Baseline {$name} must be null or a non-empty string.");
            }
        }

        if ($count < 1) {
            throw new InvalidArgumentException('Baseline violation count must be a positive integer.');
        }
    }

    public static function fromViolation(Violation $violation, string $basePath): self
    {
        $pairScoped = $violation->rule() === RuleId::UndeclaredDependencies
            && $violation->code() === 'MOD-DEPENDENCY-002';

        return new self(
            $violation->rule(),
            $violation->code(),
            $violation->severity(),
            $pairScoped || $violation->file() === null
                ? null
                : PortablePath::relative($violation->file(), $basePath),
            $violation->consumer(),
            $violation->target(),
            $pairScoped ? null : $violation->symbol(),
            1,
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        $rule = $values['rule'] ?? null;
        $code = $values['code'] ?? null;
        $severity = $values['severity'] ?? null;
        $count = $values['count'] ?? null;

        if (! is_string($rule)) {
            throw new InvalidArgumentException('Baseline violation rule must be a string.');
        }

        if (! is_string($code)) {
            throw new InvalidArgumentException('Baseline violation code must be a string.');
        }

        if (! is_string($severity)) {
            throw new InvalidArgumentException('Baseline violation severity must be a string.');
        }

        if (! is_int($count)) {
            throw new InvalidArgumentException('Baseline violation count must be a positive integer.');
        }

        try {
            $ruleId = RuleId::from($rule);
            $severityValue = Severity::from($severity);
        } catch (ValueError $exception) {
            throw new InvalidArgumentException('Baseline violation contains an unknown rule or severity.', 0, $exception);
        }

        return new self(
            $ruleId,
            $code,
            $severityValue,
            self::nullableString($values, 'file'),
            self::nullableString($values, 'consumer'),
            self::nullableString($values, 'target'),
            self::nullableString($values, 'symbol'),
            $count,
        );
    }

    public function rule(): RuleId
    {
        return $this->rule;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function withCount(int $count): self
    {
        return new self(
            $this->rule,
            $this->code,
            $this->severity,
            $this->file,
            $this->consumer,
            $this->target,
            $this->symbol,
            $count,
        );
    }

    public function identity(): string
    {
        return json_encode(
            array_slice($this->toArray(), 0, 7, true),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return array{
     *     rule: string,
     *     code: string,
     *     severity: string,
     *     file: ?string,
     *     consumer: ?string,
     *     target: ?string,
     *     symbol: ?string,
     *     count: int
     * }
     */
    public function toArray(): array
    {
        return [
            'rule' => $this->rule->value,
            'code' => $this->code,
            'severity' => $this->severity->value,
            'file' => $this->file,
            'consumer' => $this->consumer,
            'target' => $this->target,
            'symbol' => $this->symbol,
            'count' => $this->count,
        ];
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function nullableString(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        if ($value !== null && ! is_string($value)) {
            throw new InvalidArgumentException("Baseline violation {$key} must be null or a string.");
        }

        return $value;
    }
}

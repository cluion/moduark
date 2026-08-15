<?php

declare(strict_types=1);

namespace Cluion\Moduark\Architecture;

use InvalidArgumentException;

final readonly class Violation
{
    public function __construct(
        private RuleId $rule,
        private string $code,
        private Severity $severity,
        private string $message,
        private ?string $file = null,
        private ?int $line = null,
        private ?string $consumer = null,
        private ?string $target = null,
        private ?string $symbol = null,
        private ?string $suggestion = null,
    ) {
        if (preg_match('/\AMOD-[A-Z][A-Z0-9-]*-[0-9]{3}\z/', $code) !== 1) {
            throw new InvalidArgumentException(
                "Architecture violation code [{$code}] must use the MOD-NAME-000 format.",
            );
        }

        if (trim($message) === '') {
            throw new InvalidArgumentException('An architecture violation message must not be empty.');
        }

        if ($file !== null && trim($file) === '') {
            throw new InvalidArgumentException('An architecture violation file must not be empty.');
        }

        if ($line !== null && ($line < 1 || $file === null)) {
            throw new InvalidArgumentException(
                'An architecture violation line must be positive and accompanied by a file.',
            );
        }

        if ($consumer !== null && trim($consumer) === '') {
            throw new InvalidArgumentException('An architecture violation consumer must not be empty.');
        }

        if ($target !== null && trim($target) === '') {
            throw new InvalidArgumentException('An architecture violation target must not be empty.');
        }

        if ($symbol !== null && trim($symbol) === '') {
            throw new InvalidArgumentException('An architecture violation symbol must not be empty.');
        }

        if ($suggestion !== null && trim($suggestion) === '') {
            throw new InvalidArgumentException('An architecture violation suggestion must not be empty.');
        }
    }

    public function rule(): RuleId
    {
        return $this->rule;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function severity(): Severity
    {
        return $this->severity;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function file(): ?string
    {
        return $this->file;
    }

    public function line(): ?int
    {
        return $this->line;
    }

    public function consumer(): ?string
    {
        return $this->consumer;
    }

    public function target(): ?string
    {
        return $this->target;
    }

    public function symbol(): ?string
    {
        return $this->symbol;
    }

    public function suggestion(): ?string
    {
        return $this->suggestion;
    }

    /**
     * @return array{
     *     rule: string,
     *     code: string,
     *     severity: string,
     *     message: string,
     *     file: ?string,
     *     line: ?int,
     *     consumer: ?string,
     *     target: ?string,
     *     symbol: ?string,
     *     suggestion: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'rule' => $this->rule->value,
            'code' => $this->code,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'consumer' => $this->consumer,
            'target' => $this->target,
            'symbol' => $this->symbol,
            'suggestion' => $this->suggestion,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Extraction;

use InvalidArgumentException;

final readonly class ExtractabilityCheck
{
    public const PASSED = 'passed';

    public const BLOCKED = 'blocked';

    /**
     * @param list<string> $evidence
     */
    public function __construct(
        private string $code,
        private string $category,
        private string $status,
        private string $message,
        array $evidence = [],
    ) {
        if (preg_match('/\AMOD-EXTRACT-[A-Z]+-[0-9]{3}\z/', $this->code) !== 1) {
            throw new InvalidArgumentException(
                "Extractability code [{$this->code}] must use the MOD-EXTRACT-NAME-000 format.",
            );
        }

        if (preg_match('/\A[a-z][a-z_]*\z/', $this->category) !== 1) {
            throw new InvalidArgumentException('An extractability category must use snake_case.');
        }

        if (! in_array($this->status, [self::PASSED, self::BLOCKED], true)) {
            throw new InvalidArgumentException('An extractability check must be passed or blocked.');
        }

        if (trim($this->message) === '') {
            throw new InvalidArgumentException('An extractability check message must not be empty.');
        }

        foreach ($evidence as $item) {
            if (trim($item) === '') {
                throw new InvalidArgumentException('Extractability evidence must not be empty.');
            }
        }

        sort($evidence, SORT_STRING);
        $this->evidence = $evidence;
    }

    /** @var list<string> */
    private array $evidence;

    public function code(): string
    {
        return $this->code;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function message(): string
    {
        return $this->message;
    }

    /** @return list<string> */
    public function evidence(): array
    {
        return $this->evidence;
    }

    public function blocked(): bool
    {
        return $this->status === self::BLOCKED;
    }

    /**
     * @return array{
     *     code: string,
     *     category: string,
     *     status: string,
     *     message: string,
     *     evidence: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'category' => $this->category,
            'status' => $this->status,
            'message' => $this->message,
            'evidence' => $this->evidence,
        ];
    }
}

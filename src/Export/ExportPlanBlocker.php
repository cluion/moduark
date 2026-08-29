<?php

declare(strict_types=1);

namespace Cluion\Moduark\Export;

final readonly class ExportPlanBlocker
{
    /** @var list<string> */
    private array $evidence;

    /** @param list<string> $evidence */
    public function __construct(
        private string $code,
        private string $message,
        array $evidence,
    ) {
        sort($evidence, SORT_STRING);
        $this->evidence = array_values(array_unique($evidence));
    }

    public function code(): string
    {
        return $this->code;
    }

    /** @return array{code: string, message: string, evidence: list<string>} */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'evidence' => $this->evidence,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Cluion\Moduark\Lifecycle\Activation;

final readonly class ModuleActivationBlocker
{
    /**
     * @param array<string, string|list<string>> $context
     */
    public function __construct(
        private ModuleActivationBlockerCode $code,
        private string $message,
        private array $context = [],
    ) {
    }

    public function code(): ModuleActivationBlockerCode
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->message;
    }

    /** @return array<string, string|list<string>> */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * @return array{
     *     code: string,
     *     message: string,
     *     context: array<string, string|list<string>>
     * }
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code->value,
            'message' => $this->message,
            'context' => $this->context,
        ];
    }
}

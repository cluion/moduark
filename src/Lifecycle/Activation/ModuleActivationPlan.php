<?php

declare(strict_types=1);

namespace Cluion\Moduark\Lifecycle\Activation;

use Cluion\Moduark\Module;

final readonly class ModuleActivationPlan
{
    /**
     * @param list<string> $before
     * @param list<string> $after
     * @param list<class-string<Module>> $orderedModules
     * @param list<ModuleActivationBlocker> $blockers
     */
    public function __construct(
        private string $module,
        private ModuleActivationIntent $intent,
        private bool $noOp,
        private array $before,
        private array $after,
        private array $orderedModules,
        private string $activationFingerprint,
        private array $blockers = [],
    ) {
    }

    public function module(): string
    {
        return $this->module;
    }

    public function intent(): ModuleActivationIntent
    {
        return $this->intent;
    }

    public function noOp(): bool
    {
        return $this->noOp;
    }

    public function executable(): bool
    {
        return $this->blockers === [];
    }

    /** @return list<string> */
    public function before(): array
    {
        return $this->before;
    }

    /** @return list<string> */
    public function after(): array
    {
        return $this->after;
    }

    /** @return list<class-string<Module>> */
    public function orderedModules(): array
    {
        return $this->orderedModules;
    }

    public function activationFingerprint(): string
    {
        return $this->activationFingerprint;
    }

    /** @return list<ModuleActivationBlocker> */
    public function blockers(): array
    {
        return $this->blockers;
    }

    /**
     * @return array{
     *     module: string,
     *     intent: string,
     *     no_op: bool,
     *     executable: bool,
     *     before: list<string>,
     *     after: list<string>,
     *     ordered_modules: list<class-string<Module>>,
     *     activation_fingerprint: string,
     *     blockers: list<array{
     *         code: string,
     *         message: string,
     *         context: array<string, string|list<string>>
     *     }>
     * }
     */
    public function toArray(): array
    {
        return [
            'module' => $this->module,
            'intent' => $this->intent->value,
            'no_op' => $this->noOp,
            'executable' => $this->executable(),
            'before' => $this->before,
            'after' => $this->after,
            'ordered_modules' => $this->orderedModules,
            'activation_fingerprint' => $this->activationFingerprint,
            'blockers' => array_map(
                static fn (ModuleActivationBlocker $blocker): array => $blocker->toArray(),
                $this->blockers,
            ),
        ];
    }
}

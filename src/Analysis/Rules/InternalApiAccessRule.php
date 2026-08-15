<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Rules;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\ArchitectureRule;
use Cluion\Moduark\Analysis\Boundary\PublicApi;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Architecture\Violation;
use LogicException;

final readonly class InternalApiAccessRule implements ArchitectureRule
{
    public function __construct(private PublicApi $publicApi)
    {
    }

    public function id(): RuleId
    {
        return RuleId::InternalApiAccess;
    }

    public function inspect(AnalysisContext $context, Severity $severity): RuleResult
    {
        $violations = [];
        $sourceIndex = $context->sourceIndex();

        foreach ($sourceIndex->references() as $reference) {
            if ($reference->source() === $reference->target()) {
                continue;
            }

            $symbol = $sourceIndex->symbol($reference->symbol());
            $target = $context->module($reference->target());

            if ($symbol === null || $target === null) {
                throw new LogicException(
                    "Source reference [{$reference->symbol()}] has no boundary owner.",
                );
            }

            if ($this->publicApi->includes($symbol, $target)) {
                continue;
            }

            $consumer = $context->module($reference->source());
            $consumerName = $consumer?->name() ?? $context->displayName($reference->source());
            $targetName = $target->name();

            $violations[] = new Violation(
                $this->id(),
                'MOD-BOUNDARY-001',
                $severity,
                "Module [{$consumerName}] accesses an internal symbol from Module [{$targetName}].",
                $reference->file(),
                $reference->line(),
                $consumerName,
                $targetName,
                $reference->symbol(),
                "Use {$targetName}/Contracts, {$targetName}/Data, or {$targetName}/Events instead.",
            );
        }

        return new RuleResult($this->id(), $violations);
    }
}

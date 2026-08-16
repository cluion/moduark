<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Rules;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\ArchitectureRule;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Severity;
use Cluion\Moduark\Architecture\Violation;

final class ExplicitPublicExportsRule implements ArchitectureRule
{
    public function id(): RuleId
    {
        return RuleId::ExplicitPublicExports;
    }

    public function inspect(AnalysisContext $context, Severity $severity): RuleResult
    {
        $violations = [];
        $exports = [];
        $sourceIndex = $context->sourceIndex();

        foreach ($context->descriptors() as $descriptor) {
            $owner = $descriptor->moduleClass();
            $ownerName = $context->displayName($owner);
            $ownerFile = $context->module($owner)?->path();

            foreach ($descriptor->exports() as $export) {
                $symbol = $sourceIndex->symbol($export);

                if ($symbol === null) {
                    $violations[] = new Violation(
                        $this->id(),
                        'MOD-EXPORT-002',
                        $severity,
                        "Module [{$ownerName}] explicitly exports [{$export}], but it is not a symbol in indexed Module source.",
                        $ownerFile,
                        null,
                        $ownerName,
                        null,
                        $export,
                        "Export only a named class-like symbol declared inside Module [{$ownerName}].",
                    );

                    continue;
                }

                if ($symbol->owner() !== $owner) {
                    $targetName = $context->displayName($symbol->owner());
                    $violations[] = new Violation(
                        $this->id(),
                        'MOD-EXPORT-003',
                        $severity,
                        "Module [{$ownerName}] cannot explicitly export [{$symbol->name()}] because it is owned by Module [{$targetName}].",
                        $ownerFile,
                        null,
                        $ownerName,
                        $targetName,
                        $symbol->name(),
                        "Remove it from {$this->shortName($owner)}::exports(); only the owning Module may export that symbol.",
                    );

                    continue;
                }

                $exports[$owner][$this->symbolKey($symbol->name())] = true;
            }
        }

        foreach ($sourceIndex->references() as $reference) {
            if ($reference->source() === $reference->target()
                || $this->symbolKey($reference->symbol())
                    === $this->symbolKey($reference->target())
                || isset($exports[$reference->target()][$this->symbolKey($reference->symbol())])) {
                continue;
            }

            $consumer = $context->displayName($reference->source());
            $target = $context->displayName($reference->target());
            $targetEntry = $this->shortName($reference->target());
            $violations[] = new Violation(
                $this->id(),
                'MOD-EXPORT-001',
                $severity,
                "Module [{$consumer}] accesses [{$reference->symbol()}], but Module [{$target}] does not explicitly export it.",
                $reference->file(),
                $reference->line(),
                $consumer,
                $target,
                $reference->symbol(),
                "Add {$reference->symbol()}::class to {$targetEntry}::exports(), or consume a symbol that Module [{$target}] explicitly exports.",
            );
        }

        return new RuleResult($this->id(), $violations);
    }

    private function symbolKey(string $symbol): string
    {
        return strtolower(ltrim($symbol, '\\'));
    }

    private function shortName(string $class): string
    {
        $separator = strrpos($class, '\\');

        return $separator === false ? $class : substr($class, $separator + 1);
    }
}

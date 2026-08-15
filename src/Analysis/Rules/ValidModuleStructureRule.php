<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Rules;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\ArchitectureRule;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Severity;

final class ValidModuleStructureRule implements ArchitectureRule
{
    public function id(): RuleId
    {
        return RuleId::ValidModuleStructure;
    }

    public function inspect(AnalysisContext $context, Severity $severity): RuleResult
    {
        // Discovery cannot construct the analyzed registry until every entry
        // file has passed the structure and autoloadability contract.
        return new RuleResult($this->id());
    }
}

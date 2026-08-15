<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis\Rules;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\ArchitectureRule;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Severity;

final class UniqueModuleIdentityRule implements ArchitectureRule
{
    public function id(): RuleId
    {
        return RuleId::UniqueModuleIdentity;
    }

    public function inspect(AnalysisContext $context, Severity $severity): RuleResult
    {
        // ModuleRegistry rejects duplicate names and classes before an
        // AnalysisContext can be created.
        return new RuleResult($this->id());
    }
}

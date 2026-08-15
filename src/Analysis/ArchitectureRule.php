<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis;

use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Architecture\RuleResult;
use Cluion\Moduark\Architecture\Severity;

interface ArchitectureRule
{
    public function id(): RuleId;

    public function inspect(AnalysisContext $context, Severity $severity): RuleResult;
}

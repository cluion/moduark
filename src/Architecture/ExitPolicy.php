<?php

declare(strict_types=1);

namespace Cluion\Moduark\Architecture;

final class ExitPolicy
{
    public const SUCCESS = 0;

    public const VIOLATIONS_FOUND = 1;

    public const TOOL_ERROR = 2;

    /**
     * @param iterable<RuleResult> $results
     */
    public function exitCode(iterable $results): int
    {
        foreach ($results as $result) {
            if ($result->hasErrors()) {
                return self::VIOLATIONS_FOUND;
            }
        }

        return self::SUCCESS;
    }
}

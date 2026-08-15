<?php

declare(strict_types=1);

namespace Cluion\Moduark\Analysis;

use Cluion\Moduark\Architecture\Level;

interface ArchitectureCheck
{
    public function check(?Level $level = null): CheckReport;
}

<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources\Shared\Console\Commands;

use Illuminate\Console\Command;

abstract class AbstractProbe extends Command
{
    use CommandSupport;
}

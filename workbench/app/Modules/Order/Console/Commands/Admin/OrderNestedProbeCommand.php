<?php

declare(strict_types=1);

namespace Workbench\App\Modules\Order\Console\Commands\Admin;

use Illuminate\Console\Command;

final class OrderNestedProbeCommand extends Command
{
    /** @var string */
    protected $signature = 'order:nested-probe';

    /** @var string */
    protected $description = 'Probe recursive Module command discovery';

    public function handle(): int
    {
        $this->components->info('Order Module nested command ready.');

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace Workbench\App\Modules\Order\Console\Commands;

use Illuminate\Console\Command;

final class OrderProbeCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'order:probe';

    /**
     * @var string
     */
    protected $description = 'Prove that Order Module commands are registered';

    public function handle(): int
    {
        $this->components->info('Order Module command ready.');

        return self::SUCCESS;
    }
}

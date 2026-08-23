<?php

declare(strict_types=1);

namespace Workbench\App\Modules\Order\Listeners;

use Workbench\App\Modules\Order\Events\OrderProbed;

final class RecordOrderProbe
{
    public function handle(OrderProbed $event): void
    {
        $runs = config('moduark.order.listener_runs', 0);

        config([
            'moduark.order.listener_runs' => (is_int($runs) ? $runs : 0) + 1,
        ]);
    }
}

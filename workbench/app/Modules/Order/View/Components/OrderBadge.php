<?php

declare(strict_types=1);

namespace Workbench\App\Modules\Order\View\Components;

use Illuminate\View\Component;

final class OrderBadge extends Component
{
    public function render(): string
    {
        return '<span>Order component ready.</span>';
    }
}

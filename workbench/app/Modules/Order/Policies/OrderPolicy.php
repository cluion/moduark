<?php

declare(strict_types=1);

namespace Workbench\App\Modules\Order\Policies;

final class OrderPolicy
{
    public function viewAny(mixed $user): bool
    {
        return true;
    }
}

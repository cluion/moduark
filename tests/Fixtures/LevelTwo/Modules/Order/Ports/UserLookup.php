<?php

declare(strict_types=1);

namespace Tests\Fixtures\LevelTwo\Modules\Order\Ports;

interface UserLookup
{
    public function labelForOrder(int $userId): string;
}

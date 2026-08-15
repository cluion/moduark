<?php

declare(strict_types=1);

namespace Tests\Fixtures\LevelTwo\Modules\Order\Actions;

use Tests\Fixtures\LevelTwo\Modules\Order\Ports\UserLookup;

final readonly class PlaceOrder
{
    public function __construct(private UserLookup $users)
    {
    }

    public function forUser(int $userId): string
    {
        return 'Order for '.$this->users->labelForOrder($userId);
    }
}

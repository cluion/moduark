<?php

declare(strict_types=1);

namespace Tests\Fixtures\LevelTwo\Modules\Order\Adapters\User;

use Tests\Fixtures\LevelTwo\Modules\Order\Ports\UserLookup;
use Tests\Fixtures\LevelTwo\Modules\User\Contracts\UserFinder;

final readonly class UserLookupAdapter implements UserLookup
{
    public function __construct(private UserFinder $users)
    {
    }

    public function labelForOrder(int $userId): string
    {
        return $this->users->displayName($userId);
    }
}

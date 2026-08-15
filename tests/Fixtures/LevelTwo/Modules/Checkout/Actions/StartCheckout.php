<?php

declare(strict_types=1);

namespace Tests\Fixtures\LevelTwo\Modules\Checkout\Actions;

use Tests\Fixtures\LevelTwo\Modules\Checkout\Ports\UserLookup;

final readonly class StartCheckout
{
    public function __construct(private UserLookup $users)
    {
    }

    public function forUser(int $userId): string
    {
        return 'Checkout for '.$this->users->labelForCheckout($userId);
    }
}

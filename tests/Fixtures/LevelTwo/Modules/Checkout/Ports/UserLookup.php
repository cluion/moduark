<?php

declare(strict_types=1);

namespace Tests\Fixtures\LevelTwo\Modules\Checkout\Ports;

interface UserLookup
{
    public function labelForCheckout(int $userId): string;
}

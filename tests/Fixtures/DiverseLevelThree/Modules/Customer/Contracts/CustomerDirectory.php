<?php

declare(strict_types=1);

namespace Tests\Fixtures\DiverseLevelThree\Modules\Customer\Contracts;

final class CustomerDirectory
{
    public function name(int $customerId): string
    {
        return 'Customer '.$customerId;
    }
}

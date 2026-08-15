<?php

declare(strict_types=1);

namespace Tests\Fixtures\Analysis\Modules\Order\Actions;

use Tests\Fixtures\Analysis\Modules\User\Events\UserCreated;

final class PublicEventReference
{
    public function handle(UserCreated $event): UserCreated
    {
        return $event;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Notification;

use Cluion\Moduark\Capability;
use Cluion\Moduark\Module;
use Tests\Fixtures\LargeLevelTwo\Capabilities\NotificationDelivery;

final class NotificationModule extends Module
{
    /** @return list<class-string<Capability>> */
    public function provides(): array
    {
        return [NotificationDelivery::class];
    }
}

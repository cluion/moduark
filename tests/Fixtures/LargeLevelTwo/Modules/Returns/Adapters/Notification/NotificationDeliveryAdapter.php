<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Returns\Adapters\Notification;

use Tests\Fixtures\LargeLevelTwo\Modules\Returns\Ports\NotificationDelivery;
use Tests\Fixtures\LargeLevelTwo\Modules\Notification\Contracts\Notifier;

final readonly class NotificationDeliveryAdapter implements NotificationDelivery
{
    public function __construct(private Notifier $service)
    {
    }

    public function notify(string $message): string
    {
        return $this->service->send($message);
    }
}

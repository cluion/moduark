<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Returns\Ports;

interface NotificationDelivery
{
    public function notify(string $message): string;
}

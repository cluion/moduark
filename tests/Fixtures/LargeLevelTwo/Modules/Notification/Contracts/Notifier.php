<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Notification\Contracts;

final class Notifier
{
    public function send(string $message): string
    {
        return $message;
    }
}

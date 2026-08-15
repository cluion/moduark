<?php

declare(strict_types=1);

namespace Tests\Fixtures\LevelTwo\Modules\User\Services;

use Tests\Fixtures\LevelTwo\Modules\User\Contracts\UserFinder;

final class InMemoryUserFinder implements UserFinder
{
    public function displayName(int $userId): string
    {
        return "User {$userId}";
    }
}

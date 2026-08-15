<?php

declare(strict_types=1);

namespace Tests\Fixtures\LevelTwo\Modules\User\Contracts;

interface UserFinder
{
    public function displayName(int $userId): string;
}

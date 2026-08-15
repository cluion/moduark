<?php

declare(strict_types=1);

namespace Tests\Fixtures\LevelTwo\Modules\User\Providers;

use Illuminate\Support\ServiceProvider;
use Tests\Fixtures\LevelTwo\Modules\User\Contracts\UserFinder;
use Tests\Fixtures\LevelTwo\Modules\User\Services\InMemoryUserFinder;

final class UserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserFinder::class, InMemoryUserFinder::class);
    }
}

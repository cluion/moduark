<?php

declare(strict_types=1);

namespace Tests\Fixtures\LevelThree\Modules\Order\Actions;

use Tests\Fixtures\LevelThree\Modules\Order\Models\Order;
use Tests\Fixtures\LevelThree\Modules\User\Data\UserData;
use Tests\Fixtures\LevelThree\Modules\User\Models\User;

final class AccessUser
{
    public function __construct(private User $user)
    {
    }

    public function userClass(): string
    {
        return User::class;
    }

    public function createUser(): User
    {
        return new User;
    }

    public function queryUser(): mixed
    {
        return User::query();
    }

    public function localOrder(): Order
    {
        return new Order;
    }

    public function userData(UserData $data): UserData
    {
        return $data;
    }

    public function currentUserKey(): mixed
    {
        return $this->user->getKey();
    }
}

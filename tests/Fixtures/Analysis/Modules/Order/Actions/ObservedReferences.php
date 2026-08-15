<?php

declare(strict_types=1);

namespace Tests\Fixtures\Analysis\Modules\Order\Actions;

use Tests\Fixtures\Analysis\Modules\User\Attributes\UserMarker as Marker;
use Tests\Fixtures\Analysis\Modules\User\Contracts\SecondContract;
use Tests\Fixtures\Analysis\Modules\User\Contracts\UserContract as Contract;
use Tests\Fixtures\Analysis\Modules\User\Exceptions\UserFailure;
use Tests\Fixtures\Analysis\Modules\User\Internal\UnusedService;
use Tests\Fixtures\Analysis\Modules\User\Services\UserService as Service;
use Tests\Fixtures\Analysis\Modules\User\Support\UserTrait;

#[Marker]
final class ObservedReferences extends \Tests\Fixtures\Analysis\Modules\User\Base\UserBase implements Contract
{
    use UserTrait;

    public function __construct(private Service $service)
    {
    }

    public function load(
        \Tests\Fixtures\Analysis\Modules\User\Data\UserData|Service $data,
        Contract&SecondContract $contract,
    ): ?Service {
        $service = new Service;
        Service::boot();

        if ($data instanceof Service) {
            return $this->service;
        }

        try {
            if ($service === $this->service) {
                throw new UserFailure;
            }

            return $service;
        } catch (UserFailure) {
            return null;
        }
    }
}

final class LocalHelper
{
}

final class SameModuleReference
{
    public function create(): LocalHelper
    {
        return new LocalHelper;
    }
}

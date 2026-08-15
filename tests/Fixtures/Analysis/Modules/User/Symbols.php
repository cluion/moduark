<?php

declare(strict_types=1);

namespace Tests\Fixtures\Analysis\Modules\User\Attributes {
    use Attribute;

    #[Attribute]
    final class UserMarker
    {
    }
}

namespace Tests\Fixtures\Analysis\Modules\User\Base {
    abstract class UserBase
    {
    }
}

namespace Tests\Fixtures\Analysis\Modules\User\Contracts {
    interface UserContract
    {
    }

    interface SecondContract
    {
    }
}

namespace Tests\Fixtures\Analysis\Modules\User\Data {
    final readonly class UserData
    {
    }
}

namespace Tests\Fixtures\Analysis\Modules\User\Exceptions {
    use RuntimeException;

    final class UserFailure extends RuntimeException
    {
    }
}

namespace Tests\Fixtures\Analysis\Modules\User\Internal {
    final class UnusedService
    {
    }
}

namespace Tests\Fixtures\Analysis\Modules\User\Services {
    final class UserService
    {
        public static function boot(): void
        {
        }
    }
}

namespace Tests\Fixtures\Analysis\Modules\User\Support {
    trait UserTrait
    {
    }
}

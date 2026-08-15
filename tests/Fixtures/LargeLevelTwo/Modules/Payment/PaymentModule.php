<?php

declare(strict_types=1);

namespace Tests\Fixtures\LargeLevelTwo\Modules\Payment;

use Cluion\Moduark\Capability;
use Cluion\Moduark\Module;
use Tests\Fixtures\LargeLevelTwo\Capabilities\PaymentAuthorization;

final class PaymentModule extends Module
{
    /** @return list<class-string<Capability>> */
    public function provides(): array
    {
        return [PaymentAuthorization::class];
    }
}

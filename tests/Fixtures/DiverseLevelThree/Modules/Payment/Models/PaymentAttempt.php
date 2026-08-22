<?php

declare(strict_types=1);

namespace Tests\Fixtures\DiverseLevelThree\Modules\Payment\Models;

use Illuminate\Database\Eloquent\Model;

final class PaymentAttempt extends Model
{
    protected $table = 'payment_attempts';
}

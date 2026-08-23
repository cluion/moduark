<?php

declare(strict_types=1);

namespace Workbench\App\Modules\Order\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Modules\Order\Models\OrderRecord;

/** @extends Factory<OrderRecord> */
final class OrderRecordFactory extends Factory
{
    /** @var class-string<OrderRecord> */
    protected $model = OrderRecord::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['status' => 'pending'];
    }
}

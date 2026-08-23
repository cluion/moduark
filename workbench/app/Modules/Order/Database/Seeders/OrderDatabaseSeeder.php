<?php

declare(strict_types=1);

namespace Workbench\App\Modules\Order\Database\Seeders;

use Illuminate\Database\Seeder;

final class OrderDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        config(['moduark.order.seeder_ran' => true]);
    }
}

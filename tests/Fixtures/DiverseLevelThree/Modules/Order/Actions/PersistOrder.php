<?php

declare(strict_types=1);

namespace Tests\Fixtures\DiverseLevelThree\Modules\Order\Actions;

use Illuminate\Support\Facades\DB;

final class PersistOrder
{
    public function persist(int $customerId, string $dynamicTable): void
    {
        DB::transaction(static function () use ($customerId): void {
            DB::table('orders')->insert(['customer_id' => $customerId]);
            DB::table('order_items')->insert(['sku' => 'SKU-1']);
        });

        DB::transaction(static function () use ($dynamicTable): void {
            DB::table($dynamicTable)->update(['reviewed' => true]);
        });
    }
}

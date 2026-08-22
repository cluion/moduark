<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\DiverseLevelThree\External\LegacyCustomer;

Schema::create('orders', static function (Blueprint $table): void {
    $table->id();
    $table->foreignId('customer_id')->constrained('customers');
    $table->foreignIdFor(LegacyCustomer::class)->constrained();
});

Schema::create('order_items', static function (Blueprint $table): void {
    $table->id();
    $table->foreignId('order_id')->constrained('orders');
});

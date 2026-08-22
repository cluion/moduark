<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('payment_attempts', static function (Blueprint $table): void {
    $table->id();
    $table->unsignedBigInteger('order_id');
});

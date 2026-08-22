<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('customers', static function (Blueprint $table): void {
    $table->id();
});

Schema::create('customer_profiles', static function (Blueprint $table): void {
    $table->id();
    $table->foreignId('customer_id')->constrained('customers');
});

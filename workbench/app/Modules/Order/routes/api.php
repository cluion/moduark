<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::view('/__moduark-order-api', 'order::probe')
    ->name('moduark.order.api');

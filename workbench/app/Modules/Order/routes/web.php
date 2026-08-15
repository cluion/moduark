<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::view('/__moduark-order', 'order::probe')
    ->name('moduark.order.web');

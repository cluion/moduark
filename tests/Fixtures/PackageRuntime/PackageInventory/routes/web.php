<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/__moduark-package-inventory', static fn (): string => 'package-inventory')
    ->name('moduark.package-inventory');

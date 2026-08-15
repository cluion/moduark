<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Tests\Fixtures\ProbeController;

Route::get('/__moduark-probe', ProbeController::class)
    ->name('moduark.probe');

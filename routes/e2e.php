<?php

declare(strict_types=1);

use App\Http\Controllers\TestSupport\E2EController;
use Illuminate\Support\Facades\Route;

Route::get('/verification-code', [E2EController::class, 'verificationCode'])
    ->name('e2e.verification-code');

Route::get('/user', [E2EController::class, 'user'])
    ->name('e2e.user');

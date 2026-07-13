<?php

declare(strict_types=1);

use App\Http\Controllers\TestSupport\E2EController;
use Illuminate\Support\Facades\Route;

Route::get('/verification-code', [E2EController::class, 'verificationCode'])
    ->name('e2e.verification-code');

Route::get('/user', [E2EController::class, 'user'])
    ->name('e2e.user');

Route::post('/restorable-user', [E2EController::class, 'restorableUser'])
    ->name('e2e.restorable-user');

Route::post('/active-user', [E2EController::class, 'activeUser'])
    ->name('e2e.active-user');

Route::post('/onboarding-scenario-user', [E2EController::class, 'onboardingScenarioUser'])
    ->name('e2e.onboarding-scenario-user');
Route::post('/registration-throttle/reset', [E2EController::class, 'resetRegistrationThrottle'])
    ->name('e2e.registration-throttle.reset');

Route::get('/campaign-state', [E2EController::class, 'campaignState'])
    ->name('e2e.campaign-state');

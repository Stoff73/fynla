<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\TokenRefreshController;
use App\Http\Controllers\Api\V1\Mobile\DeviceController;
use App\Http\Controllers\Api\V1\Mobile\InsightsController;
use App\Http\Controllers\Api\V1\Mobile\MobileDashboardController;
use App\Http\Controllers\Api\V1\Mobile\ModuleSummaryController;
use App\Http\Controllers\Api\V1\Mobile\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\Mobile\ShareController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API v1 Routes
|--------------------------------------------------------------------------
|
| Routes registered here are loaded by the RouteServiceProvider within
| the "api" middleware group and prefixed with "/api/v1". These routes
| serve the Fynla mobile application (iOS, Android, PWA).
|
| The IdentifyMobileClient middleware is applied to all routes in this
| file, setting request attributes for mobile platform detection.
|
*/

// Health check endpoint (no auth required)
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'Fynla Mobile API v1 is operational.',
        'data' => [
            'version' => 'v1',
            'status' => 'healthy',
        ],
    ]);
})->name('api.v1.health');

// Authenticated mobile endpoints
Route::middleware('auth:sanctum')->group(function () {
    // Auth token refresh
    Route::post('/auth/refresh-token', [TokenRefreshController::class, 'refresh'])
        ->middleware('throttle:device-registration')
        ->name('api.v1.auth.refresh-token');

    // Mobile dashboard — aggregated summary of all modules
    Route::get('/mobile/dashboard', [MobileDashboardController::class, 'index'])
        ->middleware(['etag', 'throttle:mobile-dashboard'])
        ->name('api.v1.mobile.dashboard');

    // Module summaries — individual module analysis
    Route::get('/mobile/modules/{module}', [ModuleSummaryController::class, 'show'])
        ->middleware(['etag', 'throttle:mobile-dashboard'])
        ->name('api.v1.mobile.modules.show');

    // Daily insights — Fyn contextual insight
    Route::get('/mobile/insights/daily', [InsightsController::class, 'daily'])
        ->middleware(['etag', 'throttle:mobile-dashboard'])
        ->name('api.v1.mobile.insights.daily');

    // Device registration
    Route::post('/mobile/devices', [DeviceController::class, 'store'])
        ->middleware('throttle:device-registration')
        ->name('api.v1.mobile.devices.store');
    Route::get('/mobile/devices', [DeviceController::class, 'index'])
        ->name('api.v1.mobile.devices.index');
    Route::delete('/mobile/devices/{deviceId}', [DeviceController::class, 'destroy'])
        ->name('api.v1.mobile.devices.destroy');

    // Notification preferences
    Route::get('/mobile/notifications/preferences', [NotificationPreferenceController::class, 'show'])
        ->name('api.v1.mobile.notifications.preferences.show');
    Route::put('/mobile/notifications/preferences', [NotificationPreferenceController::class, 'update'])
        ->name('api.v1.mobile.notifications.preferences.update');

    // Social share
    Route::get('/mobile/share/{type}/{id?}', [ShareController::class, 'show'])
        ->name('api.v1.mobile.share');
});

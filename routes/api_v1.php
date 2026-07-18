<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\TokenRefreshController;
use App\Http\Controllers\Api\V1\Mobile\DeviceController;
use App\Http\Controllers\Api\V1\Mobile\InsightsController;
use App\Http\Controllers\Api\V1\Mobile\MobileAchievementsController;
use App\Http\Controllers\Api\V1\Mobile\MobileDashboardController;
use App\Http\Controllers\Api\V1\Mobile\ModuleSummaryController;
use App\Http\Controllers\Api\V1\Mobile\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\Mobile\ShareController;
use App\Http\Controllers\Api\V1\Native\Auth\NativeSessionController;
use App\Http\Controllers\Api\V1\Native\NativeEntitlementController;
use App\Http\Controllers\Api\V1\Native\StoreKit\AppAccountTokenController;
use App\Http\Controllers\Api\V1\Native\StoreKit\AppleReconciliationController;
use App\Http\Controllers\Api\V1\Native\StoreKit\AppleTransactionController;
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

Route::middleware(['auth:sanctum', 'native.client'])
    ->prefix('native')
    ->group(function () {
        Route::get('/health', fn () => response()->json([
            'success' => true,
            'data' => ['api_version' => 'v1'],
        ]))->name('api.v1.native.health');
    });

Route::prefix('/native/auth')->middleware('native.client')->group(function () {
    Route::post('/session/refresh', [NativeSessionController::class, 'refresh'])
        ->middleware('throttle:native-session');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/session/exchange', [NativeSessionController::class, 'exchange'])
            ->middleware('throttle:native-session');
        Route::get('/session', [NativeSessionController::class, 'show']);
        Route::delete('/session', [NativeSessionController::class, 'destroy']);
    });
});

Route::get('/native/storekit/account-token', AppAccountTokenController::class)
    ->middleware(['native.client', 'auth:sanctum', 'native.session'])
    ->name('api.v1.native.storekit.account-token');

Route::get('/native/entitlement', NativeEntitlementController::class)
    ->middleware(['native.client', 'auth:sanctum', 'native.session'])
    ->name('api.v1.native.entitlement');

Route::post('/native/storekit/transactions', AppleTransactionController::class)
    ->middleware([
        'native.client',
        'auth:sanctum',
        'native.session',
        'throttle:sensitive',
    ])
    ->name('api.v1.native.storekit.transactions.store');

Route::post('/native/storekit/reconcile', [AppleReconciliationController::class, 'reconcile'])
    ->middleware([
        'native.client',
        'auth:sanctum',
        'native.session',
        'throttle:sensitive',
    ])
    ->name('api.v1.native.storekit.reconcile');

Route::get('/native/storekit/status', [AppleReconciliationController::class, 'status'])
    ->middleware([
        'native.client',
        'auth:sanctum',
        'native.session',
        'throttle:sensitive',
    ])
    ->name('api.v1.native.storekit.status');

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

    // Mobile achievements — earned badges, next actions, financial milestones
    Route::get('/mobile/achievements', [MobileAchievementsController::class, 'index'])
        ->middleware(['etag', 'throttle:mobile-dashboard'])
        ->name('api.v1.mobile.achievements');

    // WP-5c-ii — load-more pages of completed actions (25/page)
    Route::get('/mobile/achievements/completed', [MobileAchievementsController::class, 'completed'])
        ->middleware(['etag', 'throttle:mobile-dashboard'])
        ->name('api.v1.mobile.achievements.completed');

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

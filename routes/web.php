<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Server-render per-article SEO meta tags for DB-driven insights.
// MUST be declared BEFORE the SPA catch-all below so /insights/{slug}
// runs the InsightsSeoMetaInjector middleware before returning app.blade.php.
// Bespoke insight routes handled inside the SPA still fall through to the
// catch-all — the middleware itself no-ops for bespoke or missing articles.
Route::get('/insights/{slug}', function () {
    return view('app');
})->middleware('insights.seo')->where('slug', '[a-z0-9-]+');

// Lifecycle email magic-link routes. All behind Laravel's signed middleware —
// URLs are built via URL::temporarySignedRoute() in the lifecycle campaigns
// (see app/Services/Lifecycle/Campaigns/*.php) and validated on hit.
Route::middleware('signed')->prefix('lifecycle')->group(function () {
    Route::get('/restart-trial', [\App\Http\Controllers\Lifecycle\LifecycleActionController::class, 'restartTrial'])
        ->name('lifecycle.restart-trial');
    Route::get('/apply-discount', [\App\Http\Controllers\Lifecycle\LifecycleActionController::class, 'applyDiscount'])
        ->name('lifecycle.apply-discount');
    Route::get('/feedback', [\App\Http\Controllers\Lifecycle\LifecycleActionController::class, 'feedback'])
        ->name('lifecycle.feedback');
    Route::get('/update-payment', [\App\Http\Controllers\Lifecycle\LifecycleActionController::class, 'updatePayment'])
        ->name('lifecycle.update-payment');
});
Route::post('/lifecycle/feedback-text', [\App\Http\Controllers\Lifecycle\LifecycleActionController::class, 'submitFeedbackText'])
    ->name('lifecycle.feedback-text')
    ->middleware('signed');

// Serve Vue.js SPA for all routes (catch-all)
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');

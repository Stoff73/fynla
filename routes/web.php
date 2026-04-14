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

// Lifecycle email magic-link routes.
//
// These are stub closures at this stage — they only exist so that
// URL::temporarySignedRoute() calls in the lifecycle campaigns can build
// signed URLs for the email templates. The real handlers are installed in
// Phase 9 Task 9.2 (LifecycleActionController). The names/paths here must
// stay in sync with that controller's route group.
Route::middleware('signed')->prefix('lifecycle')->group(function () {
    Route::get('/restart-trial', fn () => null)->name('lifecycle.restart-trial');
    Route::get('/apply-discount', fn () => null)->name('lifecycle.apply-discount');
    Route::get('/feedback', fn () => null)->name('lifecycle.feedback');
    Route::get('/update-payment', fn () => null)->name('lifecycle.update-payment');
});
Route::post('/lifecycle/feedback-text', fn () => null)
    ->name('lifecycle.feedback-text')
    ->middleware('signed');

// Serve Vue.js SPA for all routes (catch-all)
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');

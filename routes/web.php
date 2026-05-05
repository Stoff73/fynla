<?php

use App\Http\Controllers\FeedController;
use App\Http\Controllers\Lifecycle\LifecycleActionController;
use App\Http\Controllers\NewsletterActionController;
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
    Route::get('/restart-trial', [LifecycleActionController::class, 'restartTrial'])
        ->name('lifecycle.restart-trial');
    Route::get('/apply-discount', [LifecycleActionController::class, 'applyDiscount'])
        ->name('lifecycle.apply-discount');
    Route::get('/feedback', [LifecycleActionController::class, 'feedback'])
        ->name('lifecycle.feedback');
    Route::get('/update-payment', [LifecycleActionController::class, 'updatePayment'])
        ->name('lifecycle.update-payment');
});
Route::post('/lifecycle/feedback-text', [LifecycleActionController::class, 'submitFeedbackText'])
    ->name('lifecycle.feedback-text')
    ->middleware('signed');

// Public RSS feeds. MUST be declared BEFORE the SPA catch-all so the
// FeedController returns RSS XML instead of the Vue shell.
Route::get('/feed/news.xml', [FeedController::class, 'news'])
    ->name('feed.news');
Route::get('/feed/insights.xml', [FeedController::class, 'insights'])
    ->name('feed.insights');

// Newsletter confirm/unsubscribe — public, must be declared BEFORE the SPA catch-all
// so email-link clicks render the action page rather than the Vue shell. The 48-char
// random token IS the secret (Str::random(48) ≈ 285 bits of entropy), so no `signed`
// middleware is needed.
Route::get('/subscribe/news/confirm/{token}', [NewsletterActionController::class, 'confirm'])
    ->name('newsletter.confirm')
    ->where('token', '[A-Za-z0-9]{48}');
Route::get('/unsubscribe/news/{token}', [NewsletterActionController::class, 'unsubscribe'])
    ->name('newsletter.unsubscribe')
    ->where('token', '[A-Za-z0-9]{48}');

// Serve Vue.js SPA for all routes (catch-all)
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');

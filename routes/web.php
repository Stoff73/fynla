<?php

use App\Http\Controllers\FeedController;
use App\Http\Controllers\Lifecycle\LifecycleActionController;
use App\Http\Controllers\NewsletterActionController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

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

// Serve files from storage/app/public (cover images, user uploads, etc.).
// The classic `php artisan storage:link` symlink works fine on most hosts but
// SiteGround shared hosting 403s any traversal of public/storage even with
// `Options +FollowSymLinks` or `+SymLinksIfOwnerMatch`. This route is the
// portable fallback — only fires when no static file exists at the URL, so on
// hosts where the symlink works Apache still serves directly (faster). MUST be
// declared BEFORE the SPA catch-all.
Route::get('/storage/{path}', function (string $path) {
    abort_if(str_contains($path, '..'), 404);
    abort_unless(Storage::disk('public')->exists($path), 404);

    return Storage::disk('public')->response($path)
        ->setMaxAge(31536000)
        ->setSharedMaxAge(31536000)
        ->setPublic();
})->where('path', '.*');

// Homepage — server-render the public landing page for unauthenticated visitors;
// authenticated users get the Vue SPA directly.
// Cache-Control: public, max-age=300 lets Cloudflare / browser cache the
// unauthenticated response for 5 minutes, cutting TTFB for repeat visitors.
Route::get('/', function () {
    if (auth()->check()) {
        return view('app');
    }
    ob_start();
    include public_path('pages/index.php');
    $html = ob_get_clean();
    return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

// How It Works — server-render the public how-it-works page for unauthenticated visitors;
// authenticated users get the Vue SPA directly.
Route::get('/how-it-works', function () {
    if (auth()->check()) {
        return view('app');
    }
    ob_start();
    include public_path('pages/how-it-works.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

// Features — server-render the public features page for unauthenticated visitors;
// authenticated users get the Vue SPA directly.
Route::get('/features', function () {
    if (auth()->check()) {
        return view('app');
    }
    ob_start();
    include public_path('pages/features.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

// ─── Public marketing pages ───────────────────────────────────────────────────
// All server-render PHP for full SEO; authenticated users get the Vue SPA.

Route::get('/pricing', function () {
    if (auth()->check()) { return view('app'); }
    ob_start();
    include public_path('pages/pricing.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/about', function () {
    if (auth()->check()) { return view('app'); }
    ob_start();
    include public_path('pages/about.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/advisors', function () {
    if (auth()->check()) { return view('app'); }
    ob_start();
    include public_path('pages/advisors.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/security', function () {
    if (auth()->check()) { return view('app'); }
    ob_start();
    include public_path('pages/security.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/faq', function () {
    if (auth()->check()) { return view('app'); }
    ob_start();
    include public_path('pages/faq.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/contact', function () {
    if (auth()->check()) { return view('app'); }
    ob_start();
    include public_path('pages/contact.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/help', function () {
    if (auth()->check()) { return view('app'); }
    ob_start();
    include public_path('pages/help.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

// Why Fynla sub-pages
Route::get('/why-fynla/our-approach', function () {
    if (auth()->check()) { return view('app'); }
    ob_start();
    include public_path('pages/why-fynla/our-approach.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/why-fynla/one-platform', function () {
    if (auth()->check()) { return view('app'); }
    ob_start();
    include public_path('pages/why-fynla/one-platform.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/why-fynla/independent', function () {
    if (auth()->check()) { return view('app'); }
    ob_start();
    include public_path('pages/why-fynla/independent.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/why-fynla/alternatives', function () {
    if (auth()->check()) { return view('app'); }
    ob_start();
    include public_path('pages/why-fynla/alternatives.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

// Life stage pages
Route::get('/stage/starting-out', function () {
    if (auth()->check()) { return view('app'); }
    ob_start();
    include public_path('pages/stage/starting-out.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/stage/building-foundations', function () {
    if (auth()->check()) { return view('app'); }
    ob_start();
    include public_path('pages/stage/building-foundations.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/stage/protecting-and-growing', function () {
    if (auth()->check()) { return view('app'); }
    ob_start();
    include public_path('pages/stage/protecting-and-growing.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/stage/planning-your-future', function () {
    if (auth()->check()) { return view('app'); }
    ob_start();
    include public_path('pages/stage/planning-your-future.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/stage/enjoying-your-wealth', function () {
    if (auth()->check()) { return view('app'); }
    ob_start();
    include public_path('pages/stage/enjoying-your-wealth.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

// Feature detail pages — MUST be declared before /features catch-all (none exists, but before SPA)
Route::get('/features/net-worth-dashboard', function () {
    if (auth()->check()) { return view('app'); }
    ob_start();
    include public_path('pages/features/net-worth-dashboard.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/features/ice-letters', function () {
    if (auth()->check()) { return view('app'); }
    ob_start();
    include public_path('pages/features/ice-letters.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/features/protection-gap', function () {
    if (auth()->check()) { return view('app'); }
    ob_start();
    include public_path('pages/features/protection-gap.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/features/monte-carlo', function () {
    if (auth()->check()) { return view('app'); }
    ob_start();
    include public_path('pages/features/monte-carlo.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/features/when-can-i-retire', function () {
    if (auth()->check()) { return view('app'); }
    ob_start();
    include public_path('pages/features/when-can-i-retire.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/features/pension-tracker', function () {
    if (auth()->check()) { return view('app'); }
    ob_start();
    include public_path('pages/features/pension-tracker.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/features/iht-planning', function () {
    if (auth()->check()) { return view('app'); }
    ob_start();
    include public_path('pages/features/iht-planning.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

// ─── Compare pages ───────────────────────────────────────────────────────────
// Educational/comparison content — always serve PHP regardless of auth state.

Route::get('/compare/fynla-vs-financial-centralisation-platform', function () {
    ob_start();
    include public_path('pages/compare/fynla-vs-financial-centralisation-platform.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/compare/fynla-vs-financial-planning-platform', function () {
    ob_start();
    include public_path('pages/compare/fynla-vs-financial-planning-platform.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/compare/fynla-vs-financial-investment-platform', function () {
    ob_start();
    include public_path('pages/compare/fynla-vs-financial-investment-platform.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/compare/fynla-vs-spreadsheets', function () {
    ob_start();
    include public_path('pages/compare/fynla-vs-spreadsheets.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/compare/fynla-vs-moneyhelper', function () {
    ob_start();
    include public_path('pages/compare/fynla-vs-moneyhelper.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/compare/best-financial-planning-tools-uk', function () {
    ob_start();
    include public_path('pages/compare/best-financial-planning-tools-uk.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

// ─── Learn hub ────────────────────────────────────────────────────────────────
// Learn pages are educational content — always serve PHP regardless of auth state.
// Authenticated users reading an article don't need the SPA; there is no in-app
// equivalent of these pages.

Route::get('/learn', function () {
    ob_start();
    include public_path('pages/learn.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

// Learn — concept explainers
Route::get('/learn/what-is-an-isa', function () {
    ob_start();
    include public_path('pages/learn/what-is-an-isa.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/learn/what-is-drawdown', function () {
    ob_start();
    include public_path('pages/learn/what-is-drawdown.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/learn/should-i-consolidate-pensions', function () {
    ob_start();
    include public_path('pages/learn/should-i-consolidate-pensions.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/learn/should-i-overpay-my-mortgage', function () {
    ob_start();
    include public_path('pages/learn/should-i-overpay-my-mortgage.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/learn/what-is-salary-sacrifice', function () {
    ob_start();
    include public_path('pages/learn/what-is-salary-sacrifice.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/learn/what-is-an-lpa', function () {
    ob_start();
    include public_path('pages/learn/what-is-an-lpa.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/learn/what-is-a-sipp', function () {
    ob_start();
    include public_path('pages/learn/what-is-a-sipp.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/learn/what-is-inheritance-tax', function () {
    ob_start();
    include public_path('pages/learn/what-is-inheritance-tax.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/learn/when-should-i-make-a-will', function () {
    ob_start();
    include public_path('pages/learn/when-should-i-make-a-will.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/learn/should-i-use-a-lisa-or-isa', function () {
    ob_start();
    include public_path('pages/learn/should-i-use-a-lisa-or-isa.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/learn/when-can-i-afford-to-retire', function () {
    ob_start();
    include public_path('pages/learn/when-can-i-afford-to-retire.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/learn/glossary', function () {
    ob_start();
    include public_path('pages/learn/glossary.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

// Learn — life stage guides
Route::get('/learn/guide/starting-out', function () {
    ob_start();
    include public_path('pages/learn/guide/starting-out.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/learn/guide/building-foundations', function () {
    ob_start();
    include public_path('pages/learn/guide/building-foundations.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/learn/guide/protecting-and-growing', function () {
    ob_start();
    include public_path('pages/learn/guide/protecting-and-growing.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/learn/guide/planning-your-future', function () {
    ob_start();
    include public_path('pages/learn/guide/planning-your-future.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/learn/guide/enjoying-your-wealth', function () {
    ob_start();
    include public_path('pages/learn/guide/enjoying-your-wealth.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

// Learn — tax & allowances
Route::get('/learn/tax/pension-annual-allowance', function () {
    ob_start();
    include public_path('pages/learn/tax/pension-annual-allowance.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/learn/tax/iht-thresholds', function () {
    ob_start();
    include public_path('pages/learn/tax/iht-thresholds.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/learn/tax/capital-gains-tax', function () {
    ob_start();
    include public_path('pages/learn/tax/capital-gains-tax.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/learn/tax/tax-year-checklist', function () {
    ob_start();
    include public_path('pages/learn/tax/tax-year-checklist.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/learn/tax/isa-allowance', function () {
    ob_start();
    include public_path('pages/learn/tax/isa-allowance.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

Route::get('/calculators', function () {
    ob_start();
    include public_path('pages/calculators.php');
    return response(ob_get_clean(), 200, ['Content-Type' => 'text/html; charset=utf-8'])
        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
        ->header('Vary', 'Accept-Encoding');
});

// SP3 — mobile-first iframe scaffold. MUST be declared BEFORE the SPA catch-all
// so phone visitors get the dedicated host + isolated mobile SPA instead of the
// desktop Vue shell. /m = device-frame host; /m/landing = HTML landing (designer
// placeholder, replaceable as one file); /m/app = the isolated mobile SPA.
Route::get('/m', function () {
    return view('mobile-host');
});
Route::get('/m/landing', function () {
    return view('mobile-landing');
});
Route::get('/m/app/{any?}', function () {
    return view('mobile-app');
})->where('any', '.*');

// Serve Vue.js SPA for all routes (catch-all)
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');

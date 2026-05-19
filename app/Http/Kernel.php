<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Middleware\AdvisorImpersonationMiddleware;
use App\Http\Middleware\AdvisorMiddleware;
use App\Http\Middleware\ApiCacheHeaders;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CaptureAwcCookie;
use App\Http\Middleware\CheckFeatureAccess;
use App\Http\Middleware\CheckSubscription;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\EnsureFullEstateAccess;
use App\Http\Middleware\EnsureMFAVerified;
use App\Http\Middleware\ETagResponse;
use App\Http\Middleware\HasPermission;
use App\Http\Middleware\HasRole;
use App\Http\Middleware\IdempotencyKeyMiddleware;
use App\Http\Middleware\IdentifyMobileClient;
use App\Http\Middleware\InsightsSeoMetaInjector;
use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\PreviewWriteInterceptor;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\SanitizeInput;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TouchSessionActivity;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustHosts;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\ValidateSignature;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        TrustHosts::class,
        TrustProxies::class,
        HandleCors::class,
        PreventRequestsDuringMaintenance::class,
        ValidatePostSize::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
        SecurityHeaders::class,
        CaptureAwcCookie::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
        ],

        'api' => [
            ApiCacheHeaders::class, // Force no-store on all /api/* responses (Apache .htaccess unreliable on SiteGround vhost)
            EnsureFrontendRequestsAreStateful::class,
            ThrottleRequests::class.':api',
            SubstituteBindings::class,
            SanitizeInput::class, // SECURITY: Sanitize user input to prevent XSS
            TouchSessionActivity::class, // Refresh user_sessions.last_activity_at on each authenticated request
            AdvisorImpersonationMiddleware::class, // Swap auth user when advisor is impersonating
            PreviewWriteInterceptor::class, // Intercept writes for preview users
            CheckSubscription::class, // Feature-flagged subscription enforcement
        ],
    ];

    /**
     * The application's middleware aliases.
     *
     * Aliases may be used instead of class names to conveniently assign middleware to routes and groups.
     *
     * @var array<string, class-string|string>
     */
    protected $middlewareAliases = [
        'auth' => Authenticate::class,
        'auth.basic' => AuthenticateWithBasicAuth::class,
        'auth.session' => AuthenticateSession::class,
        'cache.headers' => SetCacheHeaders::class,
        'can' => Authorize::class,
        'guest' => RedirectIfAuthenticated::class,
        'password.confirm' => RequirePassword::class,
        'precognitive' => HandlePrecognitiveRequests::class,
        'signed' => ValidateSignature::class,
        'throttle' => ThrottleRequests::class,
        'verified' => EnsureEmailIsVerified::class,
        'admin' => IsAdmin::class,
        'mfa.verified' => EnsureMFAVerified::class,
        'role' => HasRole::class,
        'permission' => HasPermission::class,
        'identify.mobile' => IdentifyMobileClient::class,
        'etag' => ETagResponse::class,
        'advisor' => AdvisorMiddleware::class,
        'advisor.impersonate' => AdvisorImpersonationMiddleware::class,
        'feature' => CheckFeatureAccess::class,
        'estate.full' => EnsureFullEstateAccess::class,
        'insights.seo' => InsightsSeoMetaInjector::class,
        'idempotent' => IdempotencyKeyMiddleware::class,
    ];
}

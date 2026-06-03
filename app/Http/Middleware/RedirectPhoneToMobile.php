<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SP3: route phone web visitors to the dedicated mobile surface (/m).
 *
 * Desktop and tablet are untouched. Native Capacitor loads /m directly via
 * its webDir, so it never hits this middleware. Phones-only by design
 * (tablets stay on the full web app).
 */
class RedirectPhoneToMobile
{
    /**
     * Path prefixes that must never be device-redirected.
     *
     * @var array<int, string>
     */
    private const EXCLUDED_PREFIXES = [
        'm', 'm/*', 'api/*', 'admin/*', 'advisor/*',
        'lifecycle/*', 'feed/*', 'storage/*', 'subscribe/*', 'unsubscribe/*',
        'sanctum/*', 'broadcasting/*', 'livewire/*',
        'checkout', 'preview', 'preview/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Disabled by default: the public marketing site is now fully responsive,
        // so phones get a first-class landing without the /m iframe scaffold.
        // Set MOBILE_PHONE_REDIRECT=true to restore the SP3 scaffold redirect.
        if (! config('mobile.phone_redirect')) {
            return $next($request);
        }

        // Escape hatch: ?full=1 pins the visitor to the full web app via cookie.
        if ($request->query('full') === '1') {
            return $next($request)->withCookie(
                cookie('m_full_site', '1', 60 * 24 * 365, null, null, (bool) config('session.secure'), false)
            );
        }

        if (! $this->shouldRedirect($request)) {
            return $next($request);
        }

        return redirect('/m');
    }

    private function shouldRedirect(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }
        if ($request->cookie('m_full_site') === '1') {
            return false;
        }
        // Only redirect top-level HTML navigations, never XHR/asset fetches.
        if ($request->ajax() || $request->wantsJson()) {
            return false;
        }
        if (! str_contains((string) $request->header('Accept'), 'text/html')) {
            return false;
        }
        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if ($request->is($prefix)) {
                return false;
            }
        }

        return $this->isPhone((string) $request->header('User-Agent'));
    }

    private function isPhone(string $ua): bool
    {
        if ($ua === '') {
            return false;
        }
        // Phone form factor only. "Mobile" + Android/iPhone; exclude iPad/Tablet.
        if (preg_match('/\b(iPad|Tablet)\b/i', $ua)) {
            return false;
        }

        // Best-effort UA form-factor detection — some Android tablets report a
        // phone-style "Mobile" UA. ?full=1 is the user escape hatch for misclassification.
        return (bool) preg_match('/iPhone|iPod|Android.*Mobile|Windows Phone|BlackBerry|webOS/i', $ua);
    }
}

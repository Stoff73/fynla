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

    /**
     * Campaign deep-link prefixes whose path is PRESERVED through /m, so a phone
     * hitting an ad URL (e.g. /savetax) lands on the campaign funnel inside /m
     * (/m?to=/savetax) instead of the generic /m homepage. Doubles as the
     * open-redirect allowlist for the /m host's ?to= param (see isFramableTo()).
     *
     * @var array<int, string>
     */
    private const CAMPAIGN_PREFIXES = [
        'savetax', 'pensioncheck', 'biggerpension', 'paymortgage', 'managedebt', 'wealth',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Escape hatch: ?full=1 pins the visitor to the full web app via cookie.
        if ($request->query('full') === '1') {
            return $next($request)->withCookie(
                cookie('m_full_site', '1', 60 * 24 * 365, null, null, (bool) config('session.secure'), false)
            );
        }

        if (! $this->shouldRedirect($request)) {
            return $next($request);
        }

        return redirect($this->mobileRedirectTarget($request));
    }

    /**
     * Build the /m redirect, preserving campaign deep-link context. A campaign
     * path (and its query string — keeps utm/attribution) is passed as ?to= so
     * the /m host frames it; everything else lands on the plain /m homepage.
     */
    private function mobileRedirectTarget(Request $request): string
    {
        $path = $request->path();

        foreach (self::CAMPAIGN_PREFIXES as $campaign) {
            if ($path === $campaign || str_starts_with($path, $campaign.'/')) {
                $to = '/'.$path;
                $query = $request->getQueryString();
                if ($query !== null && $query !== '') {
                    $to .= '?'.$query;
                }

                return '/m?to='.urlencode($to);
            }
        }

        return '/m';
    }

    /**
     * Open-redirect guard for the /m host's ?to= param: only a same-origin
     * relative path to a known campaign page may be framed. Rejects absolute
     * URLs, protocol-relative (//evil.com) and any non-campaign path (/admin).
     */
    public static function isFramableTo(string $to): bool
    {
        if (! str_starts_with($to, '/') || str_starts_with($to, '//')) {
            return false;
        }

        $path = ltrim((string) parse_url($to, PHP_URL_PATH), '/');

        foreach (self::CAMPAIGN_PREFIXES as $campaign) {
            if ($path === $campaign || str_starts_with($path, $campaign.'/')) {
                return true;
            }
        }

        return false;
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
        // Never redirect a document being loaded INSIDE the mobile-view iframe.
        // /m hosts the real responsive funnel (homepage → /savetax → register)
        // in a same-origin iframe; without this the in-frame load of '/' would
        // itself 302 to /m and loop. iOS Safari + Chromium send
        // Sec-Fetch-Dest: iframe for framed document loads.
        $fetchDest = strtolower((string) $request->header('Sec-Fetch-Dest'));
        if ($fetchDest === 'iframe' || $fetchDest === 'frame') {
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

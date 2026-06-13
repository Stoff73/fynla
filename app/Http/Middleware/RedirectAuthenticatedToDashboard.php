<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps authenticated users off the public-facing site.
 *
 * Every public marketing, educational, and campaign page lives behind this
 * middleware. A logged-in visitor should never see the unauthenticated front
 * pages — they exist only to convert guests. The user belongs in the in-app
 * dashboard.
 *
 * Two layers, because Fynla authenticates with Sanctum bearer tokens held in
 * sessionStorage (there is no web session on a top-level navigation):
 *
 *  1. Server-side — a genuine web-session user is redirected immediately. In
 *     practice only matters for any future session-based auth; real users carry
 *     no web session on a navigation, and preview personas are deliberately
 *     EXEMPT so they can still use the landing-page persona selector.
 *  2. Client-side — for the common case (a token user doing a top-level nav, who
 *     is invisible to the web guard) we inject a tiny script at the top of
 *     <head> that bounces to the dashboard when an auth token is present. The
 *     dashboard URL is base-path-aware (root on production, "/fynla" on the
 *     csjones subdirectory deployment) so it works on both environments.
 *
 * This replaces the per-route `if (auth()->check()) { return view('app'); }`
 * branches that previously lived in every public route closure.
 */
class RedirectAuthenticatedToDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        // Layer 1 — server-side redirect for a real (non-preview) web-session
        // user. Preview personas are exempt: they authenticate on the web guard
        // and must still be able to reach the public landing / persona selector.
        $user = Auth::user();
        if ($user !== null && ! ($user->is_preview_user ?? false)) {
            return redirect($this->dashboardUrl());
        }

        // Layer 2 — token users (and guests) fall through to the rendered page;
        // a client-side guard handles the token case without a server session.
        return $this->injectClientSideGuard($next($request));
    }

    /**
     * Absolute dashboard URL, honouring any subdirectory base path in APP_URL.
     */
    private function dashboardUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/dashboard';
    }

    /**
     * Inject a base-path-aware "bounce if logged in" script as the first thing
     * inside <head>, so it runs before the public page paints.
     */
    private function injectClientSideGuard(Response $response): Response
    {
        $contentType = (string) $response->headers->get('Content-Type', '');
        if (! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $html = (string) $response->getContent();
        if ($html === '' || ! preg_match('/<head\b[^>]*>/i', $html)) {
            return $response;
        }

        $base = rtrim((string) (parse_url((string) config('app.url'), PHP_URL_PATH) ?? ''), '/');
        $script = "<script>(function(){try{if(sessionStorage.getItem('auth_token')){"
            ."window.location.replace('".$base."/dashboard');}}catch(e){}})();</script>";

        $html = preg_replace('/<head\b[^>]*>/i', '$0'.$script, $html, 1);
        $response->setContent($html);

        return $response;
    }
}

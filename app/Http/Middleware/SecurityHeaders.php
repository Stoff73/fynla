<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Security headers applied to all responses.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // HSTS in production only
        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // CSP - allows inline scripts/styles for Vue SPA, data: URIs for images (MFA QR codes)
        // Revolut embedded checkout loads embed.js from CDN, uses iframes, and makes API calls
        $revolut = 'https://sandbox-merchant.revolut.com https://merchant.revolut.com https://sandbox-assets.revolut.com https://assets.revolut.com';

        // Google Analytics (gtag.js loads from googletagmanager.com, sends data to *.google-analytics.com)
        $ga = 'https://www.googletagmanager.com https://*.google-analytics.com';

        // Plausible analytics - only widen CSP surface when analytics are enabled
        $plausible = config('analytics.enabled') ? 'https://plausible.io' : '';

        // Awin affiliate tracking - only widen CSP surface when Awin is enabled.
        // MasterTag served from dwin1.com, fallback pixel and S2S from awin1.com.
        $awin = config('awin.enabled') ? 'https://www.dwin1.com https://www.awin1.com' : '';

        // Meta Pixel — fbevents.js loads from connect.facebook.net, tracking
        // beacons and XHR fire against www.facebook.com/tr. Loaded
        // unconditionally from app.blade.php so the CSP must always allow it.
        $metaPixel = 'https://connect.facebook.net https://www.facebook.com';

        // Capacitor mobile app origins
        $capacitor = 'capacitor://localhost http://localhost';

        // In local dev, Vite serves assets from localhost:5173 and uses WebSocket for HMR
        if (app()->environment('local')) {
            $vite = 'http://localhost:5173 ws://localhost:5173 http://127.0.0.1:5173 ws://127.0.0.1:5173 http://localhost:5174 ws://localhost:5174 http://127.0.0.1:5174 ws://127.0.0.1:5174';
            $csp = "default-src 'self' {$vite}; script-src 'self' 'unsafe-inline' {$vite} {$revolut} {$plausible} {$ga} {$awin} {$metaPixel}; style-src 'self' 'unsafe-inline' {$vite} https://fonts.googleapis.com; img-src 'self' data: blob: {$vite} {$revolut} {$ga} {$awin} {$metaPixel}; font-src 'self' data: https://fonts.gstatic.com; connect-src 'self' {$vite} {$revolut} {$plausible} {$capacitor} {$ga} {$awin} {$metaPixel}; frame-src 'self' {$revolut}";
        } else {
            // Production CSP — 'unsafe-inline' required for Revolut checkout SDK and Plausible analytics.
            // TODO: Migrate to nonce-based CSP when Revolut SDK supports it (tracks Revolut SDK changelog).
            $csp = "default-src 'self'; script-src 'self' 'unsafe-inline' {$revolut} {$plausible} {$ga} {$awin} {$metaPixel}; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: blob: {$revolut} {$ga} {$awin} {$metaPixel}; font-src 'self' data: https://fonts.gstatic.com; connect-src 'self' {$revolut} {$plausible} {$capacitor} {$ga} {$awin} {$metaPixel}; frame-src 'self' {$revolut}";
        }

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self "https://sandbox-merchant.revolut.com" "https://merchant.revolut.com"), usb=(), bluetooth=()');

        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        return $response;
    }
}

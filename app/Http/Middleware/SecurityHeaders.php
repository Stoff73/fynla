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

        // In local dev, Vite serves assets from localhost:5173 and uses WebSocket for HMR
        if (app()->environment('local')) {
            $vite = 'http://localhost:5173 ws://localhost:5173 http://127.0.0.1:5173 ws://127.0.0.1:5173';
            $csp = "default-src 'self' {$vite}; script-src 'self' 'unsafe-inline' {$vite} {$revolut}; style-src 'self' 'unsafe-inline' {$vite} https://fonts.googleapis.com; img-src 'self' data: blob: {$vite} {$revolut}; font-src 'self' data: https://fonts.gstatic.com; connect-src 'self' {$vite} {$revolut}; frame-src 'self' {$revolut}";
        } else {
            $csp = "default-src 'self'; script-src 'self' 'unsafe-inline' {$revolut}; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: blob: {$revolut}; font-src 'self' data: https://fonts.gstatic.com; connect-src 'self' {$revolut}; frame-src 'self' {$revolut}";
        }

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self "https://sandbox-merchant.revolut.com" "https://merchant.revolut.com"), usb=(), bluetooth=()');

        return $response;
    }
}

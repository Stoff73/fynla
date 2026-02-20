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
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // HSTS in production only
        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // CSP - allows inline scripts/styles for Vue SPA, data: URIs for images (MFA QR codes)
        // In local dev, Vite serves assets from localhost:5173 and uses WebSocket for HMR
        if (app()->environment('local')) {
            $vite = 'http://localhost:5173 ws://localhost:5173 http://127.0.0.1:5173 ws://127.0.0.1:5173';
            $csp = "default-src 'self' {$vite}; script-src 'self' 'unsafe-inline' 'unsafe-eval' {$vite}; style-src 'self' 'unsafe-inline' {$vite} https://fonts.googleapis.com; img-src 'self' data: blob: {$vite}; font-src 'self' data: https://fonts.gstatic.com; connect-src 'self' {$vite}";
        } else {
            $csp = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: blob:; font-src 'self' data: https://fonts.gstatic.com; connect-src 'self'";
        }

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}

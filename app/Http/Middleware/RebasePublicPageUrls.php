<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rewrites root-relative URLs in the server-rendered public marketing pages
 * (public/pages/*.php) so they resolve under a subdirectory deployment.
 *
 * On production (fynla.org, served at the domain root) APP_URL has no path, so
 * this middleware is a no-op. On the dev/staging host (csjones.co/fynla) APP_URL
 * is `https://csjones.co/fynla`, so href/src/action="/..." would otherwise point
 * at the domain root and 404. We prefix them with the base path ("/fynla").
 *
 * The Vue SPA shell is left untouched — it carries its own Vite base path and is
 * identified by the `id="app"` mount node. Only the static public pages (which
 * have no such node) are rewritten.
 */
class RebasePublicPageUrls
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $base = rtrim((string) (parse_url((string) config('app.url'), PHP_URL_PATH) ?? ''), '/');
        if ($base === '') {
            return $response; // root deployment (production) — nothing to rebase
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        if (! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $html = (string) $response->getContent();
        if ($html === '' || str_contains($html, 'id="app"')) {
            return $response; // Vue SPA shell — handled by Vite base, leave alone
        }

        // Prefix root-relative href/src/action (skip protocol-relative "//" and
        // anything already under the base path).
        $html = preg_replace(
            '#\b(href|src|action)="/(?!/)(?!'.preg_quote(ltrim($base, '/'), '#').'/)#',
            '$1="'.$base.'/',
            $html
        );

        // Inline CSS url(/...) references.
        $html = preg_replace('#url\((["\']?)/(?!/)#', 'url($1'.$base.'/', $html);

        // Expose the base path to page JavaScript (navigation + fetch use it).
        $html = str_replace(
            '</head>',
            "<script>window.FYNLA_BASE='".$base."';</script></head>",
            $html
        );

        $response->setContent($html);

        return $response;
    }
}

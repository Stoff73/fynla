<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ETagResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only apply to GET requests with 200 status
        if ($request->method() !== 'GET' || $response->getStatusCode() !== 200) {
            return $response;
        }

        // Compute ETag from response body
        $content = $response->getContent();
        if ($content === false || $content === '') {
            return $response;
        }

        $etag = '"'.md5($content).'"';
        $response->headers->set('ETag', $etag);

        // Check If-None-Match header
        $ifNoneMatch = $request->header('If-None-Match');
        if ($ifNoneMatch === $etag) {
            $response->setStatusCode(304);
            $response->setContent('');
        }

        return $response;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyNativeClient
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/v1/native/*')) {
            return $next($request);
        }

        $client = strtolower(trim((string) $request->header('X-Fynla-Client')));
        $version = trim((string) $request->header('X-Fynla-Version'));
        $buildHeader = trim((string) $request->header('X-Fynla-Build'));
        $build = filter_var($buildHeader, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($client !== 'ios'
            || preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/D', $version) !== 1
            || $build === false) {
            return $this->invalidClientResponse();
        }

        $request->attributes->set('fynla_client', $client);
        $request->attributes->set('fynla_version', $version);
        $request->attributes->set('fynla_build', $build);

        return $next($request);
    }

    private function invalidClientResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'invalid_native_client',
            'message' => 'Valid native client headers are required.',
        ], 400);
    }
}

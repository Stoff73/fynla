<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureNativeDeviceLabel
{
    /**
     * Capture the submitted native label before global normalization removes
     * control characters at its boundaries.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/v1/native/auth/*') && $request->exists('device_label')) {
            $request->attributes->set('native_original_device_label', $request->input('device_label'));
        }

        return $next($request);
    }
}

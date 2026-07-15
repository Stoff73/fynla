<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pipeline;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves generated social-video clips via signed URLs pasted into the
 * tracker sheet. The signature includes a 30-day expiry (config-driven).
 *
 * Only serves files matching *.mp4 under storage/app/social/video/{slug}/;
 * any other filename or path traversal attempt 404s.
 */
class SignedClipDownloadController extends Controller
{
    public function download(Request $request, string $slug, string $filename): BinaryFileResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'Link has expired or is invalid.');

        abort_unless(preg_match('/^[a-z0-9-]{1,80}$/i', $slug), 404);
        abort_unless(preg_match('/^clip-\d{1,3}\.mp4$/', $filename), 404);

        $path = storage_path("app/social/video/{$slug}/{$filename}");
        if (! is_file($path)) {
            throw new NotFoundHttpException('Clip not found.');
        }

        return response()->file($path, [
            'Content-Type' => 'video/mp4',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}

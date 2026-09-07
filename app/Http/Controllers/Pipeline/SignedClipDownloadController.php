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
    /**
     * The one clip-filename contract. ProcessVideoJob writes clip-N.mp4 and
     * RegenerateClipJob writes clip-N-rM.mp4; the route constraint, this
     * controller, and PipelineClipApprovalsController all read this pattern —
     * three hand-copied regexes are what let regenerated clips 404.
     */
    public const FILENAME_PATTERN = 'clip-[0-9]{1,3}(-r[0-9]{1,2})?\.mp4';

    public function download(Request $request, string $slug, string $filename): BinaryFileResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'Link has expired or is invalid.');

        abort_unless(preg_match('/^[a-z0-9-]{1,80}$/i', $slug), 404);
        abort_unless(preg_match('/^'.self::FILENAME_PATTERN.'$/', $filename), 404);

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

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Pipeline;

use App\Http\Controllers\Controller;
use App\Services\Pipeline\Content\ArticleSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Receiving end of the cross-env article sync. Called by another env's
 * ArticleSyncService::push(). Validated by a shared-secret header — no
 * user auth (the shared secret IS the auth).
 *
 * Route (public): POST /api/admin/pipeline/articles/sync-inbound
 *   Header: X-Pipeline-Sync-Token: <matches this env's PIPELINE_SYNC_TOKEN>
 *   Body:   the article payload from ArticleSyncService::buildPayload()
 */
class ArticleSyncInboundController extends Controller
{
    public function receive(Request $request, ArticleSyncService $sync): JsonResponse
    {
        $expected = (string) config('pipeline.sync.inbound_token');
        if ($expected === '') {
            Log::channel('pipeline')->error('sync-inbound: PIPELINE_SYNC_TOKEN not set on this env — refusing.');
            throw new HttpException(503, 'sync endpoint not configured on this environment');
        }

        $provided = (string) $request->header('X-Pipeline-Sync-Token', '');
        if (! hash_equals($expected, $provided)) {
            Log::channel('pipeline')->warning('sync-inbound: invalid token.', [
                'from_env' => $request->header('X-Pipeline-Sync-Env'),
            ]);
            throw new HttpException(401, 'invalid sync token');
        }

        $payload = $request->all();
        $payload['_payload_hash'] = $request->header('X-Pipeline-Sync-Payload-Hash');

        $article = $sync->receive($payload);

        return response()->json([
            'success' => true,
            'article_id' => $article->id,
            'slug' => $article->slug,
        ]);
    }
}

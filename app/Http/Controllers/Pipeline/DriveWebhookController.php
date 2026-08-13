<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pipeline;

use App\Http\Controllers\Controller;
use App\Jobs\Pipeline\SyncDriveChangesJob;
use App\Models\Pipeline\DriveWatchChannel;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Receives Google Drive push notifications (changes.watch). Google POSTs here
 * whenever anything in the connected Drive changes; we verify the channel
 * token, then hand off to a queued job that lists the changes and kicks off the
 * relevant pipeline detector. The response must be fast (Google retries on
 * slow/failed acks), so no real work happens inline.
 */
class DriveWebhookController extends Controller
{
    private const PENDING_SYNC_TTL_SECONDS = 180;

    public function handle(Request $request, Dispatcher $dispatcher): Response
    {
        $expected = (string) config('pipeline.drive.webhook_token');
        $provided = (string) $request->header('X-Goog-Channel-Token', '');

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            Log::channel('pipeline')->warning('Drive webhook rejected — bad or missing channel token.');

            return response('', 403);
        }

        $channel = DriveWatchChannel::active();
        $channelId = (string) $request->header('X-Goog-Channel-ID', '');
        $resourceId = (string) $request->header('X-Goog-Resource-ID', '');

        if ($channel === null
            || ! hash_equals((string) $channel->channel_id, $channelId)
            || ! hash_equals((string) $channel->resource_id, $resourceId)) {
            Log::channel('pipeline')->warning('Drive webhook rejected — inactive or mismatched channel.');

            return response('', 403);
        }

        // The first ping after registering a channel is a "sync" handshake with
        // no real change — acknowledge it and do nothing.
        $state = (string) $request->header('X-Goog-Resource-State', '');
        if ($state !== 'sync') {
            $claimToken = bin2hex(random_bytes(32));

            if (Cache::add(SyncDriveChangesJob::PENDING_CLAIM_CACHE_KEY, $claimToken, self::PENDING_SYNC_TTL_SECONDS)) {
                try {
                    $dispatcher->dispatch(new SyncDriveChangesJob($claimToken));
                } catch (\Throwable) {
                    SyncDriveChangesJob::releasePendingClaim($claimToken);

                    return response('', 503);
                }
            }
        }

        return response('', 200);
    }
}

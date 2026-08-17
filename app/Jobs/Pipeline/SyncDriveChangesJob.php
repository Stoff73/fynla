<?php

declare(strict_types=1);

namespace App\Jobs\Pipeline;

use App\Models\Pipeline\DriveWatchChannel;
use App\Services\Pipeline\Google\DriveChangeRouter;
use App\Services\Pipeline\Google\GoogleDriveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Consumes the Drive change stream after a webhook ping: pages through
 * changes.list from the stored cursor, advances the cursor, and runs the
 * article and/or video detectors when a relevant file lands. A cache lock
 * serialises concurrent pings so the cursor isn't consumed twice.
 */
class SyncDriveChangesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const PENDING_CLAIM_CACHE_KEY = 'pipeline:drive-changes:pending';

    public int $tries = 30;

    public int $backoff = 5;

    public int $timeout = 180;

    public function __construct(private readonly string $claimToken)
    {
        $this->onQueue(config('pipeline.queue', 'pipeline'));
    }

    public static function releasePendingClaim(string $claimToken): void
    {
        Cache::restoreLock(self::PENDING_CLAIM_CACHE_KEY, $claimToken)->release();
    }

    public function handle(GoogleDriveService $drive, DriveChangeRouter $router): void
    {
        $channel = DriveWatchChannel::active();
        if ($channel === null) {
            self::releasePendingClaim($this->claimToken);

            return;
        }

        $lock = Cache::lock('pipeline:drive-changes', 240);
        if (! $lock->get()) {
            // Retain the owned pending claim until this retry drains the stream.
            $this->release(5);

            return;
        }

        self::releasePendingClaim($this->claimToken);

        try {
            $pageToken = (string) $channel->page_token;
            $allChanges = [];
            $newStart = $pageToken;

            while (true) {
                $result = $drive->listChanges($pageToken);
                $allChanges = array_merge($allChanges, $result['changes']);

                if (! empty($result['nextPageToken'])) {
                    $pageToken = (string) $result['nextPageToken'];

                    continue;
                }

                $newStart = (string) ($result['newStartPageToken'] ?? $pageToken);
                break;
            }

            // Advance the cursor first so a downstream failure doesn't re-process.
            $channel->update(['page_token' => $newStart]);

            $route = $router->classify($allChanges);

            if ($route['articles']) {
                Artisan::call('pipeline:detect-new-article-docs');
                Artisan::call('pipeline:detect-new-articles');
                Artisan::call('pipeline:detect-new-document-articles');
            }
            if ($route['videos']) {
                Artisan::call('pipeline:detect-new-videos');
            }

            Log::channel('pipeline')->info('Drive webhook processed changes.', [
                'changes' => count($allChanges),
                'ran_articles' => $route['articles'],
                'ran_videos' => $route['videos'],
            ]);
        } catch (Throwable $e) {
            Log::channel('pipeline')->error('Drive change sync failed.', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        self::releasePendingClaim($this->claimToken);
    }
}

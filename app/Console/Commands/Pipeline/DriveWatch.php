<?php

declare(strict_types=1);

namespace App\Console\Commands\Pipeline;

use App\Models\Pipeline\DriveWatchChannel;
use App\Services\Pipeline\Google\GoogleDriveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Registers (or renews / stops) the Google Drive push-notification channel so
 * new Drive files trigger the pipeline instantly instead of waiting for the
 * next poll. Drive channels expire, so the scheduler re-runs this daily to
 * re-register before Google drops the channel.
 *
 * Requires PIPELINE_DRIVE_WEBHOOK_URL (a public HTTPS endpoint — dev/live only,
 * Google cannot reach localhost) and PIPELINE_DRIVE_WEBHOOK_TOKEN.
 */
class DriveWatch extends Command
{
    protected $signature = 'pipeline:drive-watch {--stop : Stop the active channel and exit}';

    protected $description = 'Register/renew the Google Drive change webhook channel';

    public function handle(GoogleDriveService $drive): int
    {
        if (! config('pipeline.enabled')) {
            $this->warn('Pipeline is disabled (PIPELINE_ENABLED=false). Skipping.');

            return self::SUCCESS;
        }

        // Always retire the existing channel first (best-effort).
        $existing = DriveWatchChannel::active();
        if ($existing !== null && $existing->resource_id !== null) {
            try {
                $drive->stopChannel($existing->channel_id, $existing->resource_id);
            } catch (\Throwable $e) {
                Log::channel('pipeline')->warning('Drive stopChannel failed (continuing).', ['error' => $e->getMessage()]);
            }
        }

        if ($this->option('stop')) {
            DriveWatchChannel::query()->delete();
            $this->info('Drive watch channel stopped.');

            return self::SUCCESS;
        }

        $address = (string) config('pipeline.drive.webhook_url');
        $token = (string) config('pipeline.drive.webhook_token');

        // Unset webhook = this env relies on polling only. Skip quietly so the
        // daily renewal doesn't error on local/dev machines without a URL.
        if ($address === '') {
            $this->info('PIPELINE_DRIVE_WEBHOOK_URL not set — real-time trigger disabled (polling still active).');

            return self::SUCCESS;
        }
        if (! str_starts_with($address, 'https://')) {
            $this->error('PIPELINE_DRIVE_WEBHOOK_URL must be a public https:// URL (Google cannot reach localhost).');

            return self::FAILURE;
        }
        if ($token === '') {
            $this->error('PIPELINE_DRIVE_WEBHOOK_TOKEN is required (verifies incoming pings).');

            return self::FAILURE;
        }

        $pageToken = $drive->getStartPageToken();
        $channelId = (string) Str::uuid();
        $ttl = (int) config('pipeline.drive.channel_ttl_seconds', 604800);

        $channel = $drive->watchChanges($channelId, $pageToken, $address, $token, $ttl);

        DriveWatchChannel::query()->delete();
        DriveWatchChannel::create([
            'channel_id' => $channel['id'],
            'resource_id' => $channel['resourceId'],
            'page_token' => $pageToken,
            'expires_at' => $channel['expiration'] !== null
                ? now()->createFromTimestampMs($channel['expiration'])
                : now()->addSeconds($ttl),
        ]);

        $this->info("Drive watch registered. Channel {$channel['id']} → {$address}");
        Log::channel('pipeline')->info('Drive watch channel registered.', [
            'channel_id' => $channel['id'],
            'expires' => $channel['expiration'],
        ]);

        return self::SUCCESS;
    }
}

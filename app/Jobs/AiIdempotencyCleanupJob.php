<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AiRequestIdempotency;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * S0.11.3 — Daily cleanup of expired idempotency rows.
 *
 * Scheduled in app/Console/Kernel.php to run once a day. Drops every
 * row whose `expires_at` is in the past. The 24h TTL means at most a
 * day's worth of rows accumulate between runs.
 */
class AiIdempotencyCleanupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): int
    {
        return AiRequestIdempotency::query()
            ->where('expires_at', '<=', now())
            ->delete();
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands\Pipeline;

use App\Services\Pipeline\ClipApprovalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Runs every N minutes (default 5). Finds pending ClipApprovals where
 * we're within `auto_approve_minutes_before_post` of scheduled_at and
 * silently promotes them to `auto_approved`. Downstream ComposePostsJob
 * fires as soon as the article's clips are all decided.
 */
class AutoApproveClips extends Command
{
    protected $signature = 'pipeline:auto-approve-clips';

    protected $description = 'Auto-approve pending clip approvals within N minutes of their scheduled post time';

    public function handle(ClipApprovalService $service): int
    {
        if (! config('pipeline.enabled')) {
            return self::SUCCESS;
        }
        if (! config('pipeline.clip_approval.enabled', true)) {
            return self::SUCCESS;
        }

        $minutesBefore = (int) config('pipeline.clip_approval.auto_approve_minutes_before_post', 10);
        $fired = $service->autoApproveDue($minutesBefore);

        if ($fired !== []) {
            $this->info(sprintf('Auto-approved %d clip(s).', count($fired)));
            Log::channel('pipeline')->info('AutoApproveClips fired.', [
                'count' => count($fired),
            ]);
        }

        return self::SUCCESS;
    }
}

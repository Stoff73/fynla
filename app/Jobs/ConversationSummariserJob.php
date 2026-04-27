<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\AI\ConversationSummariser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * S1.3 — Dispatches `ConversationSummariser::summarise` for one conversation.
 *
 * Dispatched on `STATE_DONE` (end of onboarding) by `OnboardingChatDirector`
 * and by the every-30-minute scheduler scan for stale conversations.
 *
 * Failure mode is absorbed inside the summariser (logs + no-ops) — see
 * the docblock on `ConversationSummariser` for the rationale. We do
 * not configure `tries`/`backoff` here because re-running on a still-
 * empty conversation row is harmless and the scheduler will catch it
 * on the next sweep anyway.
 */
class ConversationSummariserJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $conversationId) {}

    public function handle(ConversationSummariser $summariser): void
    {
        $summariser->summarise($this->conversationId);
    }
}

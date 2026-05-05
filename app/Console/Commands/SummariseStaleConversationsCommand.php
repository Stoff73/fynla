<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ConversationSummariserJob;
use App\Models\AiConversation;
use Illuminate\Console\Command;

/**
 * S1.3 — Scheduled scanner that dispatches `ConversationSummariserJob`
 * for any conversation whose `last_message_at` has moved past its
 * `summarised_at` and has been quiet for at least 30 minutes.
 *
 * Runs every 30 minutes per the schedule in `App\Console\Kernel`.
 *
 * Idle threshold (30 min) keeps the summariser away from in-flight
 * conversations; the index reflects a settled state, not a moving one.
 *
 * **Resume-contract carve-out.** The canonical Two-Fyn contract says
 * "if a user leaves at any point in the conversation, the next time
 * they log in Onboarding Fyn picks up from where they left off". An
 * onboarding conversation with `onboarding_completed = false` is
 * mid-flow by definition — even if `last_message_at` is hours old,
 * the user is expected to come back and continue. Summarising it
 * mid-flow would (a) waste a provider call, (b) describe a partial
 * state that becomes immediately stale on resume, and (c) risk
 * surfacing a half-formed summary via the Sprint 1 §S1.5
 * `search_conversation_index` tool. We skip those rows; the
 * summariser fires for them later via `OnboardingChatDirector::
 * emitDoneTurn` once STATE_DONE is reached.
 */
class SummariseStaleConversationsCommand extends Command
{
    protected $signature = 'ai:conversations:summarise-stale {--idle-minutes=30 : Minimum minutes since last message before summarising}';

    protected $description = 'Dispatch the conversation-summariser job for stale conversations whose index is missing or behind the latest message';

    public function handle(): int
    {
        $idleMinutes = max(1, (int) $this->option('idle-minutes'));
        $cutoff = now()->subMinutes($idleMinutes);

        $count = 0;

        AiConversation::query()
            ->whereNotNull('last_message_at')
            ->where('last_message_at', '<=', $cutoff)
            ->where(function ($q): void {
                $q->whereNull('summarised_at')
                    ->orWhereColumn('summarised_at', '<', 'last_message_at');
            })
            ->whereNotExists(function ($q): void {
                // Resume-contract carve-out: skip in-flight onboarding
                // conversations (metadata.source = 'fyn_onboarding' AND
                // owner's onboarding_completed = false). Once onboarding
                // completes, emitDoneTurn dispatches the job directly,
                // and any subsequent post-onboarding chats fall through
                // to the normal sweep below.
                $q->select(\DB::raw(1))
                    ->from('users')
                    ->whereColumn('users.id', 'ai_conversations.user_id')
                    ->where('users.onboarding_completed', false)
                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(ai_conversations.metadata, '$.source')) = ?", ['fyn_onboarding']);
            })
            ->orderBy('last_message_at')
            ->chunkById(200, function ($chunk) use (&$count): void {
                foreach ($chunk as $conversation) {
                    ConversationSummariserJob::dispatch($conversation->id);
                    $count++;
                }
            });

        $this->info("Dispatched {$count} summariser job(s).");

        return self::SUCCESS;
    }
}

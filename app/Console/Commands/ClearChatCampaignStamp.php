<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * T23 (walk fix plan section 6b, step 2): the chat wrote
 * funnel_answers.campaign = 'savetax' on every registrant it asked a funnel
 * question, so normal registrants read as Save Tax arrivals and ended on the
 * tax plan. A /savetax registration always carries the page's income answer,
 * which the chat never asks; a row with the stamp but no income answer, whose
 * linked partner has none either, was stamped by the chat. The partner of a
 * Save Tax arrival walks that campaign (SpouseLinkingService::acceptInvitation)
 * and keeps it.
 */
class ClearChatCampaignStamp extends Command
{
    protected $signature = 'registrations:clear-chat-campaign-stamp
                            {--dry-run : List the users whose stamp would be removed, without changing them}';

    protected $description = 'Remove the Save Tax arrival stamp the onboarding chat wrote on normal registrants (T23)';

    public function handle(): int
    {
        $users = User::whereNotNull('funnel_answers')->get()
            ->filter(fn (User $user): bool => $this->stampedByChat($user))
            ->values();
        $ids = $users->pluck('id')->implode(', ');

        if ($this->option('dry-run')) {
            $this->info("Would remove the chat's Save Tax stamp from {$users->count()} user(s): {$ids}");

            return Command::SUCCESS;
        }

        foreach ($users as $user) {
            $funnel = $user->funnel_answers;
            unset($funnel['campaign']);
            $user->forceFill(['funnel_answers' => $funnel])->save();
        }

        $this->info("Removed the chat's Save Tax stamp from {$users->count()} user(s): {$ids}");
        Log::info('[ClearChatCampaignStamp] Removed the chat-written Save Tax stamp', ['user_ids' => $users->pluck('id')->all()]);

        return Command::SUCCESS;
    }

    private function stampedByChat(User $user): bool
    {
        $funnel = is_array($user->funnel_answers) ? $user->funnel_answers : [];
        if (($funnel['campaign'] ?? null) !== 'savetax' || array_key_exists('income', $funnel)) {
            return false;
        }

        $partner = $user->spouse_id !== null ? User::find($user->spouse_id) : null;
        $partnerFunnel = is_array($partner?->funnel_answers) ? $partner->funnel_answers : [];

        return ! array_key_exists('income', $partnerFunnel);
    }
}

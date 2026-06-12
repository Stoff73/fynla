<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\EvalProviderRun;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\Onboarding\OnboardingStateMachine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Create / reset the dedicated "Azlan savetax" preview persona used by the
 * golden eval scenario `azlan_savetax_journey` (Task 23).
 *
 * The seeded preview personas (PreviewUserSeeder) are all post-onboarding, so
 * they route to the read-only advice Fyn. The Azlan golden scenario needs to
 * exercise the ONBOARDING write path — which `AiChatController::sendMessage`
 * only enters when `onboarding_completed === false` AND `onboarding_fyn_step
 * !== null` AND `config('onboarding.fyn_flow_enabled')`. This command builds a
 * preview-flagged user (is_preview_user = true, preview_persona_id =
 * 'azlan_savetax') positioned at the campaign ISA-holdings step, with income +
 * employment + marital status already captured (income is the first campaign
 * section; the journey resumes from savings).
 *
 * NO MIRROR USER (canonical 0.2): Azlan IS a seeded preview persona, just one
 * created here rather than from the JSON catalogue, because it must sit
 * mid-flow. The scenario is `is_mutating: true`, so EvalRecordCommand re-runs
 * this setup before each recording and the data never bleeds between runs.
 *
 * The campaign reads `users.funnel_answers` to decide which sections to skip;
 * Azlan's funnel says they hold savings + an ISA and have a spouse, so the
 * savings and spouse sections run and the ISA-wrap / spouse strategies fire.
 *
 * Modelled on Patricia/Harold's transcript persona (2026-06-09): £110k CMO,
 * employed, married to a non-working spouse.
 */
final class EvalSetupAzlanCommand extends Command
{
    protected $signature = 'eval:setup-azlan';

    protected $description = 'Create/reset the Azlan savetax preview persona mid-campaign (golden eval Task 23).';

    public const PERSONA_ID = 'azlan_savetax';

    public function handle(): int
    {
        $this->resetExisting();

        $user = $this->createAzlan();

        $this->info("Azlan savetax preview persona ready — user id={$user->id}, step={$user->onboarding_fyn_step}, path={$user->onboarding_fyn_path}.");

        return self::SUCCESS;
    }

    /**
     * Hard-delete any prior Azlan preview user + its conversations so each
     * recording starts from an identical mid-campaign state. Mirrors the
     * force-delete discipline in ResetPreviewData (User uses SoftDeletes; a
     * soft delete would leak the email and block the next create).
     */
    private function resetExisting(): void
    {
        $existing = User::where('is_preview_user', true)
            ->where('preview_persona_id', self::PERSONA_ID)
            ->get();

        foreach ($existing as $user) {
            DB::transaction(function () use ($user): void {
                SavingsAccount::where('user_id', $user->id)->forceDelete();

                $conversationIds = AiConversation::where('user_id', $user->id)->pluck('id');

                // eval_provider_runs.conversation_id is a NOT NULL RESTRICT FK,
                // so a prior recording's run row pins its conversation and the
                // force-delete below would fail. Drop the forensic run rows for
                // this throwaway persona first to keep the reset idempotent
                // across back-to-back recordings (the command's contract).
                EvalProviderRun::whereIn('conversation_id', $conversationIds)->delete();

                AiMessage::whereIn('conversation_id', $conversationIds)->forceDelete();
                AiConversation::where('user_id', $user->id)->forceDelete();

                UserConsent::where('user_id', $user->id)->delete();

                $user->tokens()->delete();
                $user->forceDelete();
            });
        }
    }

    private function createAzlan(): User
    {
        $user = new User;

        $user->is_preview_user = true;
        $user->preview_persona_id = self::PERSONA_ID;

        $user->first_name = 'Azlan';
        $user->surname = 'Khan';
        $user->email = 'preview_'.self::PERSONA_ID.'@fynla.local';
        $user->password = Hash::make(Str::random(32)); // never used

        // £110k CMO, employed full-time, married to a non-working spouse — the
        // 2026-06-09 transcript profile. DOB is intentionally left UNSET: the
        // pensions section asks for it (STATE_CAMPAIGN_DOB), so leaving it null
        // keeps that step live rather than skipped (skipIfDobSet).
        $user->marital_status = 'married';
        // 'full_time' (not 'employed') — the savetax campaign's skipIfNotEmployed
        // only treats full_time/part_time as employed, so this keeps the
        // workplace-pension step (STATE_CAMPAIGN_OCCUPATIONAL_SCHEME) live, which
        // is where the transcript's "What's salary sacrifice?" question lands.
        $user->employment_status = 'full_time';
        $user->occupation = 'Chief Marketing Officer';
        $user->annual_employment_income = 110000;
        $user->annual_self_employment_income = 0;
        $user->annual_dividend_income = 0;
        $user->annual_rental_income = 0;
        $user->annual_interest_income = 0;
        $user->target_retirement_age = 65;

        // Funnel answers drive the campaign section walk (skipSectionIfNoCash /
        // skipIfNotMarried / skipSectionIfNoInvestments). Azlan holds savings +
        // an ISA and has a spouse, holds no investments → savings + pensions +
        // giving + spouse sections run; investments is skipped.
        $user->funnel_answers = [
            'employment' => 'employed',
            'income' => '100k_150k',
            'spouse' => 'yes',
            'assets' => ['savings', 'isa'],
        ];

        // Mid-campaign onboarding state: income captured, resuming at savings.
        $user->onboarding_completed = false;
        $user->onboarding_fyn_path = 'campaign';
        $user->onboarding_fyn_step = OnboardingStateMachine::STATE_CAMPAIGN_ISA_HOLDINGS;
        $user->onboarding_fyn_selection = null;
        $user->onboarding_fyn_context = [];

        $user->save();

        // Grant the same consents a real user grants at registration —
        // without ai_chat consent the AiChatController::sendMessage gate
        // returns 403 consent_required and the journey never starts.
        $this->grantStandardConsents($user);

        return $user;
    }

    /**
     * Mirror PreviewUserSeeder::grantStandardConsents — terms, privacy, data
     * processing, and ai_chat, all consented at the current version.
     */
    private function grantStandardConsents(User $user): void
    {
        foreach ([
            UserConsent::TYPE_TERMS,
            UserConsent::TYPE_PRIVACY,
            UserConsent::TYPE_DATA_PROCESSING,
            UserConsent::TYPE_AI_CHAT,
        ] as $consentType) {
            UserConsent::recordConsent($user->id, $consentType, true);
        }
    }
}

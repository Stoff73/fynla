<?php

declare(strict_types=1);

namespace App\Services\AI\Fyn;

use App\Models\User;
use App\Services\AI\AdvicePromptBuilder;
use App\Services\AI\MemoryRetrieverService;
use App\Services\AI\Prompts\UserContentSanitiser;
use App\Services\Onboarding\OnboardingPromptBuilder;
use App\Services\TaxConfigService;

/**
 * Builds the dynamic <context>…</context> + <user_message>…</user_message>
 * block prepended in-memory to the current user turn. Bucket membership
 * comes from FynContextSelector; block content reuses the existing
 * AdvicePromptBuilder public builders verbatim (no behavioural drift).
 *
 * The caller MUST forward the same $orchestrateAnalysis closure the legacy
 * path passes (HasAiChat::buildSystemPrompt) so the POSITION bucket gets
 * real financial context — passing null makes buildFinancialContext
 * short-circuit to its "analysis service not provided" sentinel, which
 * silently strips the user's financial position from every advice turn.
 */
final class FynContextAssembler
{
    public function __construct(
        private readonly FynContextSelector $selector,
        private readonly AdvicePromptBuilder $advice,
        private readonly MemoryRetrieverService $memory,
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function build(FynTurnContext $ctx, ?callable $orchestrateAnalysis = null): string
    {
        $buckets = $this->selector->buckets($ctx);
        $has = fn (ContextBucket $b): bool => in_array($b, $buckets, true);

        $firstName = $this->resolveFirstName($ctx->user);
        $taxYear = $this->taxConfig->getTaxYear();

        $lines = [];
        $lines[] = '<context>';
        $lines[] = "Current tax year: {$taxYear}";
        $lines[] = 'You are speaking with: '.UserContentSanitiser::wrap($firstName);
        $lines[] = $ctx->isOnboarding()
            ? 'Situation: onboarding — focus: '.$this->focusLabel($ctx->onboardingFocus)
            : 'Situation: advice';

        // IDENTITY (always present in every bucket set)
        $lines[] = '<user_profile>'."\n".$this->advice->buildUserProfile($ctx->user)."\n".'</user_profile>';
        $lines[] = '<current_context>'."\n".$this->advice->moduleContextFor($ctx->currentRoute)."\n".'</current_context>';

        // Known facts — mode-independent, included whenever non-empty
        $known = $this->memory->renderKnownFactsBlock($ctx->user, $ctx->conversation);
        if ($known !== '') {
            $lines[] = $known;
        }

        if ($has(ContextBucket::POSITION)) {
            $fin = $this->advice->buildFinancialContext($ctx->user, $orchestrateAnalysis, $ctx->classification);
            $lines[] = "<financial_context>\n{$fin}\n</financial_context>";
            $rec = $this->advice->buildExistingRecordsSummary($ctx->user, $ctx->classification);
            $lines[] = "<existing_records>\n{$rec}\n</existing_records>";
        }

        if ($has(ContextBucket::READINESS)) {
            // C1: lean per-turn block (per-user READY/BLOCKED matrix only).
            // The static NAVIGATION / BLOCKED-MODULE / MODULE-DEPENDENCY rules
            // now live once in the cached FynSystemPrompt
            // (<data_completeness_rules>) instead of ~595 tok every advice turn.
            $lines[] = $this->advice->buildPrerequisiteStateContextLean($ctx->user);
        }

        // KYC gate result (parity with legacy AdvicePromptBuilder Layer 9,
        // AdvicePromptBuilder.php:195-198). Emitted whenever the gate produced
        // prompt_text — not gated by a bucket, exactly as the legacy builder
        // appends it unconditionally — so the unified prompt asks for missing
        // data instead of advising, identically to FYN_PROMPT_ARCH=legacy.
        if ($ctx->kycResult !== null
            && isset($ctx->kycResult['prompt_text'])
            && $ctx->kycResult['prompt_text'] !== '') {
            $lines[] = $ctx->kycResult['prompt_text'];
        }

        // Billing guidance (parity with legacy AdvicePromptBuilder Layer 3c,
        // AdvicePromptBuilder.php:123-125). Classification-gated on BILLING
        // and suppressed in preview, exactly as the legacy builder injects
        // it — NOT a ContextBucket, same as the KYC layer above. Reuses the
        // legacy builder verbatim (zero drift, per this class's contract).
        // Restores the subscription/invoice journey under unified: PR #335
        // deleted <billing_guidance> from the static FynSystemPrompt without
        // a per-turn replacement, silently removing Fyn's billing surface
        // when unified became the default. See memory
        // feedback_fyn_reaches_every_surface + reference_unified_prompt_has_no_billing_layer.
        if (! $ctx->isPreview && $this->advice->isBillingQuery($ctx->classification)) {
            $lines[] = $this->advice->getBillingGuidance();
        }

        if ($has(ContextBucket::CAPTURE)) {
            $focus = (string) $ctx->onboardingFocus;
            $lines[] = FynCaptureTurnInstructions::render(
                $this->focusLabel($focus),
                implode(', ', OnboardingPromptBuilder::toolsForFocus($focus)),
            );
        }

        if ($ctx->isPreview) {
            $lines[] = '<preview_mode>'."\n"
                .'The user is previewing Fynla without a real account. You cannot create, '
                .'update, or delete any records. If they ask you to save anything, tell them '
                .'warmly it will be captured when they sign up, then continue helping with '
                .'analysis and questions.'."\n".'</preview_mode>';
        }

        $lines[] = '</context>';
        $lines[] = '<user_message>';
        $lines[] = UserContentSanitiser::clean($ctx->message);
        $lines[] = '</user_message>';

        return implode("\n", $lines);
    }

    private function resolveFirstName(User $user): string
    {
        $first = trim((string) ($user->first_name ?? ''));
        if ($first === '') {
            $parts = explode(' ', (string) $user->name);
            $first = $parts[0] !== '' ? $parts[0] : 'there';
        }

        return $first;
    }

    private function focusLabel(?string $focus): string
    {
        return match ($focus) {
            'savings', 'budgeting' => 'Cash & Savings',
            'investment' => 'Investments',
            'retirement' => 'Retirement',
            'protection' => 'Protection',
            'estate' => 'Estate Planning',
            'business' => 'Business',
            'goals' => 'Goals',
            'savetax' => 'SaveTax',
            default => (string) $focus,
        };
    }
}

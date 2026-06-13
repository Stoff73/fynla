<?php

declare(strict_types=1);

namespace App\Services\AI\Fyn;

use App\Constants\FinancialPlanningKnowledge;
use App\Constants\QuerySchemas;
use App\Models\User;
use App\Services\AI\AdvicePromptBuilder;
use App\Services\AI\MemoryRetrieverService;
use App\Services\AI\Prompts\QueryKnowledge;
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

        // Financial knowledge (parity restoration). The legacy 12-layer builder
        // injected FinancialPlanningKnowledge via buildKnowledgeBlock (Layer 8,
        // AdvicePromptBuilder.php:189); the unified cutover dropped it with no
        // per-turn replacement — same regression family as the billing layer
        // below. Classification-scoped via QueryKnowledge, advice turns only.
        if (! $ctx->isOnboarding()) {
            $knowledge = QueryKnowledge::getForClassification($ctx->classification);
            if ($knowledge !== '') {
                $lines[] = "<financial_knowledge>\n{$knowledge}\n</financial_knowledge>";
            }

            // "How do I start saving?" is a generic getting-started question, so
            // QueryClassifier rightly leaves it GENERAL (the savings keyword
            // table is deliberately narrow so "save tax" / "save for retirement"
            // are not swallowed). But a GENERAL classification injects no
            // knowledge, so Fyn answers with bare budgeting and skips the one
            // thing that must come first — the emergency-fund buffer. Inject the
            // affordability ordering for this shape so the factual answer leads
            // with the safety net before any ISA/pension push.
            $gettingStarted = $this->savingsGettingStartedDirective($ctx);
            if ($gettingStarted !== null) {
                $lines[] = $gettingStarted;
            }

            $lines[] = $this->voicingRules();
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

    /**
     * When a GENERAL-classified turn is a generic "how do I start saving?"
     * question, return a focused affordability block that puts the emergency
     * fund first; otherwise null.
     *
     * Gated to GENERAL so it never competes with a real savings/tax/retirement
     * classification (those carry their own knowledge). The topic guard then
     * excludes "save tax", "save for retirement", "invest", etc. — those use
     * the word "save" but are not the getting-started-with-a-buffer question,
     * and forcing emergency-fund framing onto them would be wrong.
     */
    private function savingsGettingStartedDirective(FynTurnContext $ctx): ?string
    {
        if (($ctx->classification['primary'] ?? null) !== QuerySchemas::GENERAL) {
            return null;
        }

        $message = mb_strtolower($ctx->message);

        // Must be a getting-started-with-saving shape.
        $isGettingStarted = preg_match(
            '/\b(start|begin|started|get into|getting into|better at|new to|how (do|to|can) i)\b.{0,30}\bsav(e|ing)\b/i',
            $message
        ) === 1
            || preg_match('/\bsav(e|ing)\b.{0,20}\b(properly|better|for the first time|from scratch)\b/i', $message) === 1;

        if (! $isGettingStarted) {
            return null;
        }

        // Topic guard — a "save" question that is really about tax, pensions,
        // investments, ISAs, or mortgages is not the emergency-fund question.
        if (preg_match('/\b(tax|pension|retire|retirement|invest|isa|mortgage|gia)\b/i', $message) === 1) {
            return null;
        }

        return "<savings_getting_started>\n"
            ."The user is asking how to start saving. Lead with the affordability ordering below — name the emergency-fund buffer (around three to six months of essential outgoings, more if self-employed) as the FIRST priority, before any Individual Savings Account or pension contribution. Then cover high-interest debt, then regular saving into the right wrapper.\n\n"
            .FinancialPlanningKnowledge::getAffordabilityRules()."\n"
            .'</savings_getting_started>';
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

    private function voicingRules(): string
    {
        return <<<'RULES'
<voicing_rules>
Claim tiers govern how you state guidance:
- MECHANICAL claims (allowance arithmetic, tax-band maths, carry-forward totals, taper effects, recommendations marked claim_tier=mechanical): state them directly and quantified with the user's own figures, and show the working inline — e.g. "£110,000 − £10,000 contribution = £100,000, restoring your full Personal Allowance — worth around £6,000 this year." Always quote threshold figures retrieved from get_tax_information.
- JUDGEMENT claims (investment selection, trust structures, drawdown choices, anything marked claim_tier=judgement): hedge them ("you may want to consider", "one option might be") and signpost regulated advice.
Proactivity: after fully answering the user's question, you MAY surface AT MOST ONE additional high-value strategy from the recommendations if it is clearly relevant to what they asked — lead with the pound impact, keep it to two sentences, and never let it crowd the actual answer.
Ambiguity: if a figure the user gave you is ambiguous in a way that changes the answer (e.g. "£90,000" — total or per year?), ask the one clarifying question BEFORE computing anything from it.
</voicing_rules>
RULES;
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

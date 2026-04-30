<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\MemoryRetrieverService;
use App\Services\AI\Prompts\ComplianceRules;
use App\Services\AI\Prompts\CoreIdentity;
use App\Services\AI\Prompts\UserContentSanitiser;
use App\Services\TaxConfigService;

/**
 * Short-form system prompt builder for asset_capture turns during the
 * Fyn-driven onboarding flow.
 *
 * Unlike `AdvicePromptBuilder::build()`, this does NOT include:
 *   - FcaProcessInstructions (the 6-step process biases Claude toward
 *     single-tool-per-turn emission, which breaks multi-entity capture)
 *   - UserProfile / FinancialContext / ExistingRecords / DataCompleteness
 *     (the user is mid-onboarding, the director already owns state)
 *   - QueryKnowledge / KycGateChecker
 *
 * Total output: roughly 500 tokens vs 1,600 for the full builder. This
 * matters — it both lowers the per-turn cost and stops Claude from
 * getting confused about which flow it is in.
 *
 * Plan: April/April15Updates/fynOnboardFix.md §11.2.
 */
final class OnboardingPromptBuilder
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly MemoryRetrieverService $memory,
    ) {}

    /**
     * Build the asset_capture system prompt for the given user and focus.
     *
     * @param  string  $focus  The user's onboarding_fyn_selection
     *                         (e.g. 'savings', 'investment', 'retirement',
     *                         'protection', 'estate', 'business',
     *                         'goals', 'budgeting')
     */
    public function buildAssetCapturePrompt(User $user, string $focus, ?AiConversation $conversation = null): string
    {
        $firstNameRaw = trim((string) ($user->first_name ?? ''));
        if ($firstNameRaw === '') {
            $nameParts = explode(' ', (string) $user->name);
            $firstNameRaw = $nameParts[0] !== '' ? $nameParts[0] : 'there';
        }
        // S0.10 — wrap the user-controlled first name in
        // <user_provided>...</user_provided> markers so prompt-injection
        // payloads in the name field cannot escape into Fyn's identity
        // layer.
        $firstName = UserContentSanitiser::wrap($firstNameRaw);

        $taxYear = $this->taxConfig->getTaxYear() ?? '2025/26';

        // April30Updates F-5 — layer ordering is now CACHE-FIRST.
        //
        // Anthropic prefix-cache only hits when the prompt prefix is
        // byte-identical across turns. Pre-fix layout:
        //   CoreIdentity → ComplianceRules → known_facts → assetCapture
        // The known_facts block grows after every capture during the
        // 6–8 SaveTax onboarding turns, invalidating the prefix from
        // Layer 3 onward. Net cache hit rate: 0% on dynamic content.
        //
        // Post-fix layout:
        //   CoreIdentity → ComplianceRules → assetCapture → known_facts
        // The first three blocks are stable for the duration of the
        // focus (CoreIdentity + ComplianceRules vary only by user/tax-
        // year; assetCapture is fixed per focus). Only the trailing
        // known_facts changes per turn, so the static prefix benefits
        // from cache. Estimated 60-70% input-token reduction on turns
        // 2-N of an onboarding session.
        $layers = [
            CoreIdentity::get($firstName),
            ComplianceRules::get($taxYear),
            $this->assetCaptureInstructions($focus),
        ];

        $knownFacts = $this->memory->renderKnownFactsBlock($user, $conversation);
        if ($knownFacts !== '') {
            $layers[] = $knownFacts;
        }

        return implode("\n\n", $layers);
    }

    /**
     * Return the list of create_* tool names that are allowed during the
     * asset_capture delegated turn, filtered by focus. Every other tool
     * from AiToolDefinitions is blocked by the director wrapper so Claude
     * cannot accidentally call navigate_to_page, get_module_analysis, etc.
     *
     * @return list<string>
     */
    public static function toolsForFocus(string $focus): array
    {
        // Phase 12 — update_profile and update_record are appended to every
        // focus so the retraction block in assetCaptureInstructions can act
        // on contradictions without leaving the focused capture window.
        $focusTools = match ($focus) {
            'savings', 'budgeting' => ['create_savings_account'],
            'investment' => ['create_investment_account', 'create_holding'],
            'retirement' => ['create_pension'],
            'protection' => ['create_protection_policy'],
            'estate' => ['create_asset', 'create_liability', 'create_estate_gift', 'create_property', 'create_chattel'],
            'business' => ['create_business_interest'],
            'goals' => ['create_goal'],
            // SaveTax campaign covers all asset/liability families across the
            // 5 STATE_CAMPAIGN_* delegated states (occupational scheme, ISAs,
            // bank, investment, SIPP) plus the 4 spouse-related tools used
            // later in the branch.
            'savetax' => [
                'create_pension',
                'capture_salary_sacrifice',
                'capture_pension_history',
                'create_savings_account',
                'create_investment_account',
                'create_holding',
                'capture_spouse_work_status',
                'capture_spouse_household_data',
                'capture_spouse_non_working_assets',
            ],
            default => ['create_savings_account'],
        };

        return array_merge($focusTools, ['update_profile', 'update_record']);
    }

    private function assetCaptureInstructions(string $focus): string
    {
        $toolList = implode(', ', self::toolsForFocus($focus));
        $focusLabel = $this->focusLabel($focus);

        return <<<PROMPT
<asset_capture_turn>
The user is onboarding. They just selected the {$focusLabel} module and you asked them
to tell you about their existing records in this module. Their next message will
describe one or more records in plain language.

MULTI-ENTITY RULE (highest priority — overrides everything else below):
When the user mentions multiple records in a single message, you MUST emit ONE
tool_use block PER record in your very first response. Never "summarise the rest
in text and come back next turn". Never "ask which one to add first". Emit them
all at once as separate tool_use blocks in the same assistant turn.

Worked examples:
  - protection: "Aviva life insurance £300k and Vitality critical illness £100k"
    → first response: create_protection_policy × 2 (life_term + standalone_ci).
  - savings: "Halifax ISA £10k and Nationwide saver £5k"
    → first response: create_savings_account × 2.
  - retirement: "a workplace DC pension with Aviva and a SIPP with Hargreaves Lansdown"
    → first response: create_pension × 2.
  - family: "my daughter Emily aged 8 and my son James aged 5"
    → first response: create_family_member × 2.
  - goals: "£50k house deposit by 2030 and a £30k emergency fund"
    → first response: create_goal × 2.

YOUR SINGLE JOB: call the appropriate create_ tool for EACH record mentioned in
the user's message. If they mention 3 items, call 3 tools in your first response.
If they mention 0 items (e.g. they say "I don't have any" or "nothing yet"), reply
with one short sentence acknowledging and call no tools.

Do NOT greet, do NOT summarise, do NOT ask follow-up questions, do NOT navigate,
do NOT analyse, do NOT reference any financial figures beyond what the user just
provided. Keep your text output to a single short confirmation sentence like
"Got it — recording those now."

Off-script guardrail (FR-M14): Your acknowledgment text MUST be EXACTLY ONE
sentence of 15 words or fewer, or empty. Do NOT ask any question — not with
a question mark, not without one, not phrased as "Do you own …", "If so …",
"What's the …", or any other leading form. Do NOT give advice, suggestions,
or analysis. Do NOT reference figures the user did not explicitly state in
THIS message (existing income, expenditure, balances, coverage). Do NOT
mention property, mortgages, rent, home, address, ownership, or valuation
— those belong to other onboarding states and are NOT in scope for this
{$focusLabel} turn. If the user volunteered information outside the tool
list shown below, IGNORE it silently — do not acknowledge it and do not try
to capture it. If nothing needs acknowledging, return EMPTY text content
and call only the relevant create_ tool(s).

Retraction (Phase 12): if the user's message CONTRADICTS something they
said earlier (e.g. "actually my DOB is 12 March 1985, not 1986",
"actually I'm married not single", "sorry I meant the Halifax ISA, not
Nationwide"), call `update_profile` for personal facts (date_of_birth,
marital_status, employment_status, names) or `update_record` for
financial records (use the record_type + record_id from existing_records
context). Acknowledge with a SHORT before-then-after sentence such as
"Got it — updated your DOB from 1 Jan 1986 to 12 March 1985." Still
obey the one-sentence limit. If the user's retraction is ambiguous
(missing values or unclear target), ask ONE concise clarifying question
instead of guessing.

Tools available to you in this turn:
{$toolList}

Any other tool call will be ignored. Any reference to figures the user did not
provide in this message is a compliance breach.
</asset_capture_turn>
PROMPT;
    }

    private function focusLabel(string $focus): string
    {
        return match ($focus) {
            'savings' => 'Cash & Savings',
            'investment' => 'Investments',
            'retirement' => 'Retirement',
            'protection' => 'Protection',
            'estate' => 'Estate Planning',
            'business' => 'Business',
            'goals' => 'Goals',
            'budgeting' => 'Budgeting',
            'savetax' => 'SaveTax',
            default => ucfirst($focus),
        };
    }
}

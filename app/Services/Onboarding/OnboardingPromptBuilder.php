<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\User;
use App\Services\AI\Prompts\ComplianceRules;
use App\Services\AI\Prompts\CoreIdentity;
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
    ) {}

    /**
     * Build the asset_capture system prompt for the given user and focus.
     *
     * @param  string  $focus  The user's onboarding_fyn_selection
     *                         (e.g. 'savings', 'investment', 'retirement',
     *                         'protection', 'estate', 'business',
     *                         'goals', 'budgeting')
     */
    public function buildAssetCapturePrompt(User $user, string $focus): string
    {
        $nameParts = explode(' ', (string) $user->name);
        $firstName = $nameParts[0] !== '' ? $nameParts[0] : 'there';

        $taxYear = $this->taxConfig->getTaxYear() ?? '2025/26';

        $layers = [
            CoreIdentity::get($firstName),
            ComplianceRules::get($taxYear),
            $this->assetCaptureInstructions($focus),
        ];

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
        return match ($focus) {
            'savings', 'budgeting' => ['create_savings_account'],
            'investment' => ['create_investment_account', 'create_holding'],
            'retirement' => ['create_pension'],
            'protection' => ['create_protection_policy'],
            'estate' => ['create_asset', 'create_liability', 'create_estate_gift', 'create_property', 'create_chattel'],
            'business' => ['create_business_interest'],
            'goals' => ['create_goal'],
            default => ['create_savings_account'],
        };
    }

    private function assetCaptureInstructions(string $focus): string
    {
        $toolList = implode(', ', self::toolsForFocus($focus));
        $focusLabel = $this->focusLabel($focus);

        return <<<PROMPT
<asset_capture_turn>
The user is onboarding. They just selected the {$focusLabel} module and you asked them
to tell you about their existing holdings in this module. Their next message will
describe one or more holdings in plain language.

YOUR SINGLE JOB: call the appropriate create_ tool for EACH holding mentioned in
the user's message. If they mention 3 items, call 3 tools in your first response.
If they mention 0 items (e.g. they say "I don't have any" or "nothing yet"), reply
with one short sentence acknowledging and call no tools.

Multi-entity rule: when the user mentions multiple holdings in a single message,
you MUST emit one tool_use block per holding in your very first response. Do not
summarise the rest in text and come back for them on the next turn — emit them
all at once.

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
            default => ucfirst($focus),
        };
    }
}

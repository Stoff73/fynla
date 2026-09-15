<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\AdviceFyn;
use App\Services\AI\Fyn\FynCaptureTurnInstructions;
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
            'savings' => ['create_savings_account'],
            // Budgeting asks for monthly spending, so it needs the expenditure
            // tool. Aliased to savings until 2026-08-18, it ran as a Cash &
            // Savings turn: the user answered "£5000 per month", the model had
            // no tool that fit, and its only scripted exit was the
            // prompt-injection refusal — then "Sorry, I didn't catch that", on
            // every retry, forever (live: user 80, conversation 67). Same
            // failure the 'pensioncheck' arm below was added to stop.
            'budgeting' => ['set_expenditure'],
            'investment' => ['create_investment_account', 'create_holding'],
            'retirement' => ['create_pension'],
            'protection' => ['create_protection_policy'],
            // create_business_interest is here because the estate intro invites
            // business interests in the same breath as valuables and gifts; the
            // capture block tells the model to ignore anything outside its tool
            // list, so without it the user's answer was silently dropped.
            'estate' => ['create_asset', 'create_liability', 'create_estate_gift', 'create_property', 'create_chattel', 'create_business_interest'],
            'business' => ['create_business_interest'],
            // The focus is 'Goals & Life Events'; both need a tool.
            'goals' => ['create_goal', 'create_life_event'],
            // SaveTax campaign covers all asset/liability families across the
            // 5 STATE_CAMPAIGN_* delegated states (occupational scheme, ISAs,
            // bank, investment, SIPP) plus the 4 spouse-related tools used
            // later in the branch.
            'savetax' => [
                'create_pension',
                'capture_salary_sacrifice',
                'capture_pension_history',
                'capture_charitable_giving',
                'create_savings_account',
                'create_investment_account',
                'create_holding',
                'capture_spouse_work_status',
                'capture_spouse_household_data',
                'capture_spouse_non_working_assets',
            ],
            // PensionCheck campaign covers the pension-only delegated states —
            // campaign_occupational_scheme (workplace DC + salary sacrifice),
            // campaign2_pension_pots (pot-value update_record, appended below),
            // campaign_pension_contribs (personal/SIPP), campaign2_pension_db
            // (Defined Benefit), campaign2_flexible_access (flag update_record),
            // campaign2_spouse_pensions (spouse-owned create_pension). Without a
            // 'pensioncheck' arm the focus fell to the savings default, so these
            // states had no pension tool and the model security-refused.
            'pensioncheck' => [
                'create_pension',
                'capture_salary_sacrifice',
            ],
            // The advice -> capture handoff, which is not a module walk: its
            // scope is whatever the user just asked to record. Advice Fyn
            // strips every write tool, so a request it cannot route lands
            // here, and the prompt must offer the whole write surface or the
            // model is told its own tools are out of scope and refuses.
            // Sourced from AdviceFyn::WRITE_TOOLS so the list the prompt
            // advertises and the list the turn can dispatch cannot drift.
            'inline_capture' => array_values(array_diff(AdviceFyn::WRITE_TOOLS, ['navigate_to_page'])),
            default => ['create_savings_account'],
        };

        return array_values(array_unique(array_merge($focusTools, ['update_profile', 'update_record'])));
    }

    /**
     * Rule 20 — the capture-turn rule block has ONE home,
     * {@see FynCaptureTurnInstructions}, whose own docblock records that it was
     * lifted verbatim from this method. The two copies had already drifted:
     * this one had never gained the INTENT EXCEPTION block, so under the legacy
     * prompt architecture the model was free to invent a provider or value to
     * satisfy a tool call — the compliance breach that block exists to stop.
     * Rendering from the shared source ends the drift instead of re-syncing it.
     */
    private function assetCaptureInstructions(string $focus): string
    {
        return FynCaptureTurnInstructions::render(
            self::focusLabel($focus),
            implode(', ', self::toolsForFocus($focus)),
        );
    }

    /**
     * The one focus → label map. FynContextAssembler held a second copy that
     * called budgeting "Cash & Savings"; every consumer reads this one now.
     */
    public static function focusLabel(string $focus): string
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
            'pensioncheck' => 'Pension Check',
            // Not a module — the handoff turn records whatever was asked for.
            'inline_capture' => 'Your Records',
            default => ucfirst($focus),
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Services\AI\Prompts;

/**
 * Alternative Layer 5/6/7 for brand-new users with zero financial data.
 *
 * Replaces FinancialContext + ExistingRecords + DataCompleteness when the user
 * has not entered ANY income, savings, investments, or pensions. Without this
 * guard, orchestrateAnalysis runs every module agent against empty data and
 * Fyn hallucinates specific figures ("Your £75,000 income...") that the user
 * never provided.
 *
 * This layer is used for any non-onboarding Fyn chat by a user with no data.
 * The Fyn-driven onboarding flow uses OnboardingPromptBuilder instead — it
 * bypasses this layer entirely because the director owns the state machine.
 */
final class EmptyDataGuard
{
    public static function get(): string
    {
        return <<<'PROMPT'
<new_user_state>
This user has ZERO financial data: no income, no savings, no investments, no pensions, no protection, no property, no goals. You know nothing about their finances yet.

THE FOLLOWING RULES OVERRIDE EVERY OTHER INSTRUCTION IN THIS PROMPT (including the FCA process and the "use tools proactively" rule in <available_actions>):

1. NEVER reference any specific figure (£, %, age, years). Any number you produce would be fabricated.
2. NEVER call get_module_analysis, get_recommendations, generate_financial_plan, get_tax_information, or run any analysis tool. There is nothing to analyse.
3. NEVER call create_savings_account, create_investment_account, create_pension, create_property, create_protection_policy, create_goal, create_liability, or any other create_* tool UNLESS the user has explicitly told you about a specific holding in the current message (e.g. "I have a £10k ISA at Vanguard").
4. NEVER call update_profile UNLESS the user has just given you a specific personal value (DOB, marital status, employment, income, expenditure) in their current message.

Keep your answers general and helpful. Invite the user to tell you about their finances so you can help them specifically.
</new_user_state>
PROMPT;
    }
}

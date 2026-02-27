<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Agents\CoordinatingAgent;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AiContextBuilder
{
    public function __construct(
        private readonly CoordinatingAgent $coordinatingAgent,
    ) {}

    /**
     * Build the system prompt for the AI assistant.
     */
    public function buildSystemPrompt(User $user, ?string $currentRoute = null): string
    {
        $profile = $this->buildUserProfile($user);
        $financialSummary = $this->buildFinancialSummary($user);
        $moduleContext = $this->getModuleContext($currentRoute);
        $isPreview = $user->is_preview_user;

        $prompt = <<<PROMPT
You are Fynla Assistant, a professional financial planning assistant built into the Fynla application. You help users understand and improve their financial position using their actual data.

## Rules
- Always use British English spelling (e.g. "personalised", "optimise", "analyse")
- Never use acronyms in responses — always spell out in full (e.g. "Inheritance Tax" not "IHT", "Individual Savings Account" not "ISA")
- Format all currency values in GBP with commas (e.g. £1,250.00)
- You are not a regulated financial adviser — never give specific product recommendations or say "you should buy/sell X"
- Frame guidance as "you may want to consider" or "it could be worth exploring"
- Be concise and direct — avoid filler phrases
- When discussing the user's data, reference specific numbers from their profile
- If you do not have enough data to answer a question, say so honestly

## User Profile
{$profile}

## Financial Summary
{$financialSummary}
PROMPT;

        if ($moduleContext) {
            $prompt .= "\n\n## Current Page Context\n{$moduleContext}";
        }

        if ($isPreview) {
            $prompt .= "\n\n## Preview Mode\nThis user is in preview mode exploring the application. You cannot create, update, or delete any records for them. If they ask you to create a goal, account, or other record, explain that this feature is available when they create a real account. You can still analyse their data and answer questions.";
        }

        $prompt .= "\n\n## Available Actions\n";
        $prompt .= "- You can navigate the user to relevant pages using the navigate_to_page tool\n";
        $prompt .= "- You can fetch detailed analysis for any financial module using get_module_analysis\n";
        $prompt .= "- You can run what-if scenarios to show impact of changes using run_what_if_scenario\n";
        $prompt .= "- You can look up current UK tax information using get_tax_information\n";

        if (! $isPreview) {
            $prompt .= "- You can create financial goals using create_goal\n";
            $prompt .= "- You can create life events using create_life_event\n";
        }

        return $prompt;
    }

    /**
     * Build user profile section of the system prompt.
     */
    private function buildUserProfile(User $user): string
    {
        $lines = [];
        $lines[] = "- Name: {$user->name}";

        if ($user->date_of_birth) {
            $age = $user->date_of_birth->age;
            $lines[] = "- Age: {$age}";
        }

        if ($user->employment_status) {
            $lines[] = "- Employment: {$user->employment_status}";
        }

        if ($user->marital_status) {
            $lines[] = "- Marital status: {$user->marital_status}";
        }

        $totalIncome = $this->calculateTotalIncome($user);
        if ($totalIncome > 0) {
            $formatted = number_format($totalIncome, 2);
            $lines[] = "- Total annual income: £{$formatted}";
        }

        $totalExpenditure = $this->calculateTotalExpenditure($user);
        if ($totalExpenditure > 0) {
            $formatted = number_format($totalExpenditure, 2);
            $lines[] = "- Monthly expenditure: £{$formatted}";
        }

        if ($user->retirement_date) {
            $lines[] = "- Target retirement date: {$user->retirement_date->format('j F Y')}";
        }

        // Family context
        $children = $user->familyMembers()->where('relationship', 'child')->count();
        if ($children > 0) {
            $lines[] = "- Children: {$children}";
        }

        return implode("\n", $lines);
    }

    /**
     * Build a concise financial summary from the coordinating agent.
     */
    private function buildFinancialSummary(User $user): string
    {
        try {
            $analysis = $this->coordinatingAgent->orchestrateAnalysis($user->id);
        } catch (\Exception $e) {
            Log::warning('[AiContextBuilder] Failed to build financial summary', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return 'Financial summary unavailable — analysis could not be completed.';
        }

        $lines = [];

        // Available surplus
        $surplus = $analysis['available_surplus'] ?? 0;
        if ($surplus !== 0) {
            $formatted = number_format(abs($surplus), 2);
            $label = $surplus >= 0 ? 'Monthly surplus' : 'Monthly shortfall';
            $lines[] = "- {$label}: £{$formatted}";
        }

        // Module highlights
        $modules = $analysis['module_analysis'] ?? [];

        if (isset($modules['savings']['data']['emergency_fund_months'])) {
            $months = $modules['savings']['data']['emergency_fund_months'];
            $lines[] = "- Emergency fund: {$months} months of cover";
        }

        if (isset($modules['retirement']['data']['projected_annual_income'])) {
            $income = number_format($modules['retirement']['data']['projected_annual_income'], 0);
            $lines[] = "- Projected retirement income: £{$income} per year";
        }

        if (isset($modules['estate']['data']['iht_liability'])) {
            $iht = number_format($modules['estate']['data']['iht_liability'], 0);
            $lines[] = "- Estimated Inheritance Tax liability: £{$iht}";
        }

        // Top recommendations
        $recommendations = $analysis['ranked_recommendations'] ?? [];
        if (! empty($recommendations)) {
            $top = array_slice($recommendations, 0, 3);
            $lines[] = '';
            $lines[] = 'Top recommendations:';
            foreach ($top as $i => $rec) {
                $title = $rec['title'] ?? $rec['recommendation'] ?? 'Recommendation';
                $num = $i + 1;
                $lines[] = "{$num}. {$title}";
            }
        }

        return ! empty($lines) ? implode("\n", $lines) : 'No financial data available yet.';
    }

    /**
     * Get context about the current page/module the user is viewing.
     */
    private function getModuleContext(?string $currentRoute): ?string
    {
        if (! $currentRoute) {
            return null;
        }

        $contexts = [
            '/dashboard' => 'The user is on their Dashboard — the main overview of their financial position.',
            '/net-worth' => 'The user is viewing their Net Worth summary across all asset categories.',
            '/net-worth/property' => 'The user is viewing their property portfolio.',
            '/net-worth/investments' => 'The user is viewing their investment accounts.',
            '/net-worth/pensions' => 'The user is viewing their pension holdings.',
            '/net-worth/savings' => 'The user is viewing their savings accounts.',
            '/net-worth/cash' => 'The user is viewing their cash holdings.',
            '/net-worth/chattels' => 'The user is viewing their valuable possessions.',
            '/net-worth/business-interests' => 'The user is viewing their business interests.',
            '/net-worth/liabilities' => 'The user is viewing their liabilities and debts.',
            '/retirement' => 'The user is on the Retirement Planning page — pension projections and retirement income planning.',
            '/savings' => 'The user is on the Savings page — emergency fund analysis and savings optimisation.',
            '/investment' => 'The user is on the Investment page — portfolio analysis, asset allocation, and performance.',
            '/protection' => 'The user is on the Protection page — life, income, and critical illness cover analysis.',
            '/estate' => 'The user is on the Estate Planning page — Inheritance Tax, wills, trusts, and gifting strategies.',
            '/goals' => 'The user is on the Goals & Life Events page — financial goals tracking and future event planning.',
        ];

        return $contexts[$currentRoute] ?? null;
    }

    /**
     * Calculate total annual income from all sources.
     */
    private function calculateTotalIncome(User $user): float
    {
        return (float) $user->annual_employment_income
            + (float) $user->annual_self_employment_income
            + (float) $user->annual_rental_income
            + (float) $user->annual_dividend_income
            + (float) $user->annual_interest_income
            + (float) $user->annual_other_income
            + (float) $user->annual_trust_income;
    }

    /**
     * Calculate total monthly expenditure.
     */
    private function calculateTotalExpenditure(User $user): float
    {
        if ($user->monthly_expenditure && $user->monthly_expenditure > 0) {
            return (float) $user->monthly_expenditure;
        }

        if ($user->annual_expenditure && $user->annual_expenditure > 0) {
            return (float) $user->annual_expenditure / 12;
        }

        return 0;
    }
}

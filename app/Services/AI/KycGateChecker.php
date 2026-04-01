<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Constants\QuerySchemas;
use App\Models\User;
use App\Services\PrerequisiteGateService;

/**
 * Checks KYC (Know Your Customer) data completeness before the AI gives advice.
 *
 * Pre-computed in PHP, injected into the system prompt as <kyc_status>.
 * If data is missing, Fyn asks the user to provide it instead of giving advice.
 *
 * Bypass types (data_entry, navigation) always pass — no KYC needed.
 * General/factual queries also pass — no advice being given.
 */
class KycGateChecker
{
    public function __construct(
        private readonly PrerequisiteGateService $prerequisiteGate,
    ) {}

    /**
     * Check KYC requirements for a classification.
     *
     * @return array{passed: bool, missing: string[], prompt_text: string}
     */
    public function check(User $user, array $classification): array
    {
        $primary = $classification['primary'];

        // Bypass types skip KYC entirely
        if (QuerySchemas::isBypassType($primary)) {
            return $this->pass();
        }

        // General/factual queries don't need KYC
        if ($primary === QuerySchemas::GENERAL) {
            return $this->pass();
        }

        $allMissing = [];

        // Check universal requirements
        $universalMissing = $this->checkUniversalRequirements($user);
        $allMissing = array_merge($allMissing, $universalMissing);

        // Check module-specific requirements for ALL classified modules
        $modules = QuerySchemas::getModulesForClassification($classification);
        foreach ($modules as $module) {
            $moduleMissing = $this->checkModuleRequirements($user, $module);
            $allMissing = array_merge($allMissing, $moduleMissing);
        }

        // Deduplicate
        $allMissing = array_values(array_unique($allMissing));

        if (empty($allMissing)) {
            return $this->passWithSummary($user, $classification);
        }

        return $this->blocked($allMissing);
    }

    /**
     * Check universal requirements needed for all advice types.
     */
    private function checkUniversalRequirements(User $user): array
    {
        $missing = [];

        if (! $user->date_of_birth) {
            $missing[] = 'Date of birth';
        }

        if (! $user->marital_status) {
            $missing[] = 'Marital status';
        }

        if (! $user->employment_status) {
            $missing[] = 'Employment status';
        }

        $totalIncome = (float) $user->annual_employment_income
            + (float) $user->annual_self_employment_income
            + (float) $user->annual_rental_income
            + (float) $user->annual_dividend_income
            + (float) $user->annual_interest_income
            + (float) $user->annual_other_income
            + (float) $user->annual_trust_income;

        if ($totalIncome <= 0) {
            $missing[] = 'Annual income (at least one income source)';
        }

        $hasExpenditure = ($user->monthly_expenditure && $user->monthly_expenditure > 0)
            || ($user->annual_expenditure && $user->annual_expenditure > 0);
        if (! $hasExpenditure) {
            $expenditureProfile = $user->expenditureProfile ?? null;
            if (! $expenditureProfile || ! ($expenditureProfile->total_monthly_expenditure > 0)) {
                $missing[] = 'Monthly expenditure';
            }
        }

        return $missing;
    }

    /**
     * Check module-specific requirements using PrerequisiteGateService.
     */
    private function checkModuleRequirements(User $user, string $module): array
    {
        // Map module names to PrerequisiteGateService actions
        $actionMap = [
            'protection' => 'protection',
            'savings' => 'savings',
            'retirement' => 'retirement',
            'investment' => 'investment',
            'estate' => 'estate',
            'goals' => 'goals',
            'tax' => 'tax_optimisation',
        ];

        $action = $actionMap[$module] ?? null;
        if (! $action) {
            return [];
        }

        $gate = $this->prerequisiteGate->enforce($action, $user);

        if ($gate['can_proceed']) {
            return [];
        }

        return $gate['missing'] ?? [];
    }

    /**
     * KYC passed — return with a brief data summary for the AI.
     */
    private function passWithSummary(User $user, array $classification): array
    {
        $modules = $classification['modules'] ?? [];
        $moduleList = ! empty($modules) ? implode(', ', $modules) : 'general';

        return [
            'passed' => true,
            'missing' => [],
            'prompt_text' => "<kyc_status>\nKYC CHECK: PASSED. Sufficient data available for {$moduleList} analysis. Proceed with advice using the FCA 6-step process.\n</kyc_status>",
        ];
    }

    /**
     * KYC blocked — return with missing data list and instructions.
     */
    private function blocked(array $missing): array
    {
        $missingList = implode("\n", array_map(fn ($item) => "- {$item}", $missing));

        $promptText = <<<PROMPT
<kyc_status>
KYC CHECK: BLOCKED. The following data is missing and must be provided before you can give advice:

{$missingList}

INSTRUCTIONS: Do NOT give advice, estimates, or general guidance on this topic. Instead:
1. Explain to the user that you need more information before you can give personalised advice
2. List each missing item clearly
3. Offer to help the user enter the data conversationally (e.g. "I can help you add your income details right now — just tell me your annual salary")
4. If appropriate, navigate the user to the relevant page to enter the data
</kyc_status>
PROMPT;

        return [
            'passed' => false,
            'missing' => $missing,
            'prompt_text' => $promptText,
        ];
    }

    /**
     * Bypass — KYC not required.
     */
    private function pass(): array
    {
        return [
            'passed' => true,
            'missing' => [],
            'prompt_text' => '',
        ];
    }
}

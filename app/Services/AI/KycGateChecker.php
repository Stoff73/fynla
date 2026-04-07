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

        // Deduplicate: universal checks take priority (they have correct routes).
        // Module gates may duplicate universal items with different wording.
        $seen = [];
        $deduplicated = [];
        foreach ($allMissing as $item) {
            $label = is_array($item) ? $item['label'] : $item;
            // Normalise for dedup: lowercase, check if any existing label is a substring
            $labelLower = strtolower($label);
            $isDuplicate = false;
            foreach ($seen as $seenLabel) {
                if (str_contains($labelLower, strtolower($seenLabel))
                    || str_contains(strtolower($seenLabel), $labelLower)) {
                    $isDuplicate = true;
                    break;
                }
            }
            if (! $isDuplicate) {
                $seen[] = $label;
                $deduplicated[] = $item;
            }
        }
        $allMissing = $deduplicated;

        if (empty($allMissing)) {
            return $this->passWithSummary($user, $classification);
        }

        return $this->blocked($allMissing);
    }

    /**
     * Check universal requirements needed for all advice types.
     * Returns array of ['label' => string, 'route' => string] for each missing item.
     */
    private function checkUniversalRequirements(User $user): array
    {
        $missing = [];

        if (! $user->date_of_birth) {
            $missing[] = ['label' => 'Date of birth', 'route' => '/profile'];
        }

        if (! $user->marital_status) {
            $missing[] = ['label' => 'Marital status', 'route' => '/profile'];
        }

        if (! $user->employment_status) {
            $missing[] = ['label' => 'Employment status', 'route' => '/profile'];
        }

        $totalIncome = (float) $user->annual_employment_income
            + (float) $user->annual_self_employment_income
            + (float) $user->annual_rental_income
            + (float) $user->annual_dividend_income
            + (float) $user->annual_interest_income
            + (float) $user->annual_other_income
            + (float) $user->annual_trust_income;

        if ($totalIncome <= 0) {
            $missing[] = ['label' => 'Annual income (at least one income source)', 'route' => '/valuable-info?section=income'];
        }

        $hasExpenditure = ($user->monthly_expenditure && $user->monthly_expenditure > 0)
            || ($user->annual_expenditure && $user->annual_expenditure > 0);
        if (! $hasExpenditure) {
            $expenditureProfile = $user->expenditureProfile ?? null;
            if (! $expenditureProfile || ! ($expenditureProfile->total_monthly_expenditure > 0)) {
                $missing[] = ['label' => 'Monthly expenditure', 'route' => '/valuable-info?section=expenditure'];
            }
        }

        return $missing;
    }

    /**
     * Check module-specific requirements using PrerequisiteGateService.
     * Returns array of ['label' => string, 'route' => string].
     */
    private function checkModuleRequirements(User $user, string $module): array
    {
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

        // Build structured missing items from gate results
        $missing = [];
        $gateActions = $gate['required_actions'] ?? [];
        $gateMissing = $gate['missing'] ?? [];

        // Pair missing labels with routes from required_actions
        foreach ($gateMissing as $i => $label) {
            $route = isset($gateActions[$i]) ? ($gateActions[$i]['route'] ?? null) : null;
            $missing[] = [
                'label' => $label,
                'route' => $route ?? $this->getDefaultRouteForModule($module),
            ];
        }

        return $missing;
    }

    /**
     * Default navigation route for a module when no specific route is available.
     */
    private function getDefaultRouteForModule(string $module): string
    {
        return match ($module) {
            'protection' => '/protection',
            'savings' => '/net-worth/cash',
            'retirement' => '/net-worth/retirement',
            'investment' => '/net-worth/investments',
            'estate' => '/estate',
            'goals' => '/goals',
            'tax' => '/valuable-info?section=income',
            'income' => '/valuable-info?section=income',
            default => '/dashboard',
        };
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
     * KYC blocked — return with missing data list, routes, and mandatory navigation instructions.
     */
    private function blocked(array $missing): array
    {
        // Build the missing list with exact routes
        $missingLines = [];
        $navigationInstructions = [];
        $seenRoutes = [];

        foreach ($missing as $item) {
            $label = is_array($item) ? $item['label'] : $item;
            $route = is_array($item) ? ($item['route'] ?? null) : null;

            $missingLines[] = "- {$label}".($route ? " → navigate to {$route}" : '');

            if ($route && ! isset($seenRoutes[$route])) {
                $seenRoutes[$route] = true;
                $navigationInstructions[] = "- Use navigate_to_page with route_path \"{$route}\" for: {$label}";
            }
        }

        $missingList = implode("\n", $missingLines);
        $navList = ! empty($navigationInstructions) ? implode("\n", $navigationInstructions) : '';

        $promptText = <<<PROMPT
<kyc_status>
KYC CHECK: BLOCKED. The following data is missing and must be provided before you can give advice:

{$missingList}

MANDATORY INSTRUCTIONS — follow these exactly, do not deviate:
1. Do NOT give advice, estimates, or general guidance on this topic
2. Explain clearly what data is missing and why it is needed for personalised advice
3. Offer to help the user enter the data conversationally
4. Navigate the user to the EXACT page listed above using navigate_to_page — do NOT navigate anywhere else

MANDATORY NAVIGATION (use these exact routes):
{$navList}
</kyc_status>
PROMPT;

        $missingLabels = array_map(fn ($item) => is_array($item) ? $item['label'] : $item, $missing);

        return [
            'passed' => false,
            'missing' => $missingLabels,
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

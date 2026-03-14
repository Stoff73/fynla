<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Constants\EstateDefaults;
use App\Services\TaxConfigService;

class EstateOnboardingFlow
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Get the estate planning onboarding steps with configuration
     */
    public function getSteps(): array
    {
        $steps = [
            'personal_info' => [
                'name' => 'personal_info',
                'title' => 'Personal Information',
                'description' => 'Tell us about yourself to help us tailor your estate plan',
                'order' => 1,
                'required' => true,
                'skip_reason' => 'Personal information helps us calculate your estate value and available tax reliefs. Without this, we cannot provide personalized estate planning advice.',
                'fields' => [
                    'date_of_birth' => ['required' => true],
                    'gender' => ['required' => true],
                    'marital_status' => ['required' => true],
                    'national_insurance_number' => ['required' => false],
                    'address_line_1' => ['required' => true],
                    'city' => ['required' => true],
                    'postcode' => ['required' => true],
                    'phone' => ['required' => false],
                ],
            ],
            'family_info' => [
                'name' => 'family_info',
                'title' => 'Family & Beneficiaries',
                'description' => 'Tell us about your family members and who you want to benefit from your estate',
                'order' => 2,
                'required' => false,
                'skip_reason' => 'Beneficiary information helps us calculate available reliefs (like spouse exemption and RNRB) and model different bequest scenarios to minimize IHT.',
                'fields' => [
                    'spouse_info' => ['required' => false],
                    'children_info' => ['required' => false],
                    'other_beneficiaries' => ['required' => false],
                ],
            ],
            'income' => [
                'name' => 'income',
                'title' => 'Employment & Income',
                'description' => 'Your income and employment details help us understand your financial position',
                'order' => 7,
                'required' => true,
                'skip_reason' => 'Income information is essential for calculating your estate\'s Inheritance Tax liability and understanding your protection needs. Without this, we cannot provide accurate IHT projections or determine if your family would be financially secure.',
                'fields' => [
                    'occupation' => ['required' => false],
                    'employer' => ['required' => false],
                    'industry' => ['required' => false],
                    'employment_status' => ['required' => true],
                    'annual_employment_income' => ['required' => false],
                    'annual_self_employment_income' => ['required' => false],
                    'annual_dividend_income' => ['required' => false],
                    'annual_other_income' => ['required' => false],
                ],
            ],
            'expenditure' => [
                'name' => 'expenditure',
                'title' => 'Household Expenditure',
                'description' => 'Help us understand your spending patterns for accurate financial planning',
                'order' => 8,
                'required' => true,
                'skip_reason' => 'Understanding your expenditure helps us calculate your emergency fund needs, discretionary income, and protection requirements. Without this, we cannot accurately assess your financial resilience.',
                'fields' => [
                    'food_groceries' => ['required' => false],
                    'transport_fuel' => ['required' => false],
                    'healthcare_medical' => ['required' => false],
                    'insurance' => ['required' => false],
                    'mobile_phones' => ['required' => false],
                    'internet_tv' => ['required' => false],
                    'subscriptions' => ['required' => false],
                    'clothing_personal_care' => ['required' => false],
                    'entertainment_dining' => ['required' => false],
                    'holidays_travel' => ['required' => false],
                    'pets' => ['required' => false],
                    'childcare' => ['required' => false],
                    'school_fees' => ['required' => false],
                    'children_activities' => ['required' => false],
                    'other_expenditure' => ['required' => false],
                    'monthly_expenditure' => ['required' => true],
                    'annual_expenditure' => ['required' => true],
                ],
            ],
            'domicile_info' => [
                'name' => 'domicile_info',
                'title' => 'Domicile Information',
                'description' => 'Your domicile status affects your UK tax liability and IHT calculations',
                'order' => 3,
                'required' => true,
                'skip_reason' => 'Domicile status is crucial for IHT planning. Non-UK domiciled individuals have different IHT rules and exemptions. Without this information, we cannot calculate your accurate IHT liability.',
                'fields' => [
                    'country_of_birth' => ['required' => true],
                    'uk_arrival_date' => ['required' => false], // Only if non-UK born
                    'years_uk_resident' => ['required' => false], // Auto-calculated
                    'domicile_status' => ['required' => false], // Auto-determined
                    'deemed_domicile_date' => ['required' => false], // Auto-calculated
                ],
            ],
            'assets' => [
                'name' => 'assets',
                'title' => 'Assets & Wealth',
                'description' => 'Tell us about your properties, investments, and other assets',
                'order' => 4,
                'required' => true,
                'skip_reason' => 'Your assets form the basis of your taxable estate. Without this information, we cannot calculate your potential IHT liability, which is the primary purpose of estate planning.',
                'fields' => [
                    'has_properties' => ['required' => false],
                    'has_investments' => ['required' => false],
                    'has_savings' => ['required' => false],
                    'has_business_interests' => ['required' => false],
                    'has_chattels' => ['required' => false],
                ],
            ],
            'liabilities' => [
                'name' => 'liabilities',
                'title' => 'Liabilities & Debts',
                'description' => 'Tell us about mortgages, loans, and other debts',
                'order' => 5,
                'required' => false,
                'skip_reason' => 'Liabilities reduce your taxable estate for IHT purposes. Skipping this may result in overestimating your IHT bill and missing potential tax savings.',
                'fields' => [
                    'has_mortgages' => ['required' => false],
                    'has_loans' => ['required' => false],
                    'has_credit_cards' => ['required' => false],
                ],
            ],
            'protection_policies' => [
                'name' => 'protection_policies',
                'title' => 'Protection Policies',
                'description' => 'Tell us about your existing life insurance and protection coverage',
                'order' => 6,
                'required' => false,
                'skip_reason' => 'Protection policies can provide liquidity for your estate to pay IHT bills. Knowing about these helps us ensure your beneficiaries have enough funds to settle tax liabilities.',
                'fields' => [
                    'has_life_insurance' => ['required' => false],
                    'life_insurance_policies' => ['required' => false],
                ],
            ],
            'will_info' => [
                'name' => 'will_info',
                'title' => 'Will Information',
                'description' => 'Tell us about your will and estate planning documents',
                'order' => 9,
                'required' => false,
                'skip_reason' => 'Will status is crucial for probate readiness scoring and understanding how your estate would be distributed. This helps identify gaps in your estate plan.',
                'fields' => [
                    'has_will' => ['required' => false],
                    'will_last_updated' => ['required' => false],
                    'executor_details' => ['required' => false],
                ],
            ],
            'trust_info' => [
                'name' => 'trust_info',
                'title' => 'Trust Information',
                'description' => 'Tell us about any trusts you have created or benefit from',
                'order' => 10,
                'required' => false,
                'conditional' => true, // Only show if certain conditions are met
                'skip_reason' => 'Existing trusts can affect your IHT calculation due to Potentially Exempt Transfers (PETs) and Chargeable Lifetime Transfers (CLTs). Skipping this may lead to inaccurate tax projections.',
                'fields' => [
                    'has_trusts' => ['required' => false],
                    'trust_details' => ['required' => false],
                ],
            ],
            'completion' => [
                'name' => 'completion',
                'title' => 'Setup Complete',
                'description' => 'You\'re all set! Here\'s what happens next',
                'order' => 11,
                'required' => true,
                'skip_reason' => null, // Cannot skip completion
                'fields' => [],
            ],
        ];

        // Sort steps by 'order' field
        uasort($steps, function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        return $steps;
    }

    /**
     * Get steps filtered based on progressive disclosure rules
     */
    public function getFilteredSteps(array $userData): array
    {
        $allSteps = $this->getSteps();
        $filteredSteps = [];

        foreach ($allSteps as $stepKey => $step) {
            if ($this->shouldShowStep($stepKey, $userData)) {
                $filteredSteps[$stepKey] = $step;
            }
        }

        return $filteredSteps;
    }

    /**
     * Determine if a step should be shown based on progressive disclosure rules
     */
    public function shouldShowStep(string $stepName, array $userData): bool
    {
        $steps = $this->getSteps();

        if (! isset($steps[$stepName])) {
            return false;
        }

        $step = $steps[$stepName];

        // Always show non-conditional steps
        if (! isset($step['conditional']) || ! $step['conditional']) {
            return true;
        }

        // Trust Info - show only if:
        // 1. User indicated trust ownership elsewhere
        // 2. Or estimated estate value > £2m (RNRB taper threshold)
        if ($stepName === 'trust_info') {
            $hasTrusts = $userData['has_trusts'] ?? false;
            $estateValue = $this->calculateEstimatedEstateValue($userData);

            return $hasTrusts || $estateValue > EstateDefaults::RNRB_TAPER_THRESHOLD;
        }

        // Family Info - show spouse section only if married
        if ($stepName === 'family_info') {
            $maritalStatus = $userData['marital_status'] ?? null;

            // Always show family info, but content will be filtered inside the component
            return true;
        }

        return true;
    }

    /**
     * Calculate estimated estate value from user data
     */
    private function calculateEstimatedEstateValue(array $userData): float
    {
        $estimates = $this->taxConfig->get('estate.onboarding_estimates', [
            'property' => 300000,
            'investment' => 50000,
            'savings' => 25000,
            'business' => 100000,
        ]);

        $estimatedValue = 0.0;

        // Add property values (uses average UK property price as rough estimate)
        if (isset($userData['has_properties']) && $userData['has_properties']) {
            $estimatedValue += (float) ($estimates['property'] ?? 300000);
        }

        // Add investment values (conservative estimate)
        if (isset($userData['has_investments']) && $userData['has_investments']) {
            $estimatedValue += (float) ($estimates['investment'] ?? 50000);
        }

        // Add savings (conservative estimate)
        if (isset($userData['has_savings']) && $userData['has_savings']) {
            $estimatedValue += (float) ($estimates['savings'] ?? 25000);
        }

        // Add business interests (conservative estimate)
        if (isset($userData['has_business_interests']) && $userData['has_business_interests']) {
            $estimatedValue += (float) ($estimates['business'] ?? 100000);
        }

        return $estimatedValue;
    }

    /**
     * Get the skip reason text for a specific step
     */
    public function getSkipReason(string $stepName): ?string
    {
        $steps = $this->getSteps();

        return $steps[$stepName]['skip_reason'] ?? null;
    }

    /**
     * Get the next step after the current one
     */
    public function getNextStep(string $currentStep, array $userData): ?string
    {
        $steps = $this->getFilteredSteps($userData);
        $stepKeys = array_keys($steps);
        $currentIndex = array_search($currentStep, $stepKeys);

        if ($currentIndex === false || $currentIndex === count($stepKeys) - 1) {
            return null;
        }

        return $stepKeys[$currentIndex + 1];
    }

    /**
     * Get the previous step before the current one
     */
    public function getPreviousStep(string $currentStep, array $userData): ?string
    {
        $steps = $this->getFilteredSteps($userData);
        $stepKeys = array_keys($steps);
        $currentIndex = array_search($currentStep, $stepKeys);

        if ($currentIndex === false || $currentIndex === 0) {
            return null;
        }

        return $stepKeys[$currentIndex - 1];
    }
}

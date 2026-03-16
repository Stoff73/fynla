<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Estate\Gift;
use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\Estate\Will;
use App\Models\LetterToSpouse;
use App\Models\User;

/**
 * Data readiness gate for the Estate Planning module.
 *
 * Assesses whether a user has entered sufficient data for meaningful
 * estate planning analysis. Returns blocking issues (analysis cannot proceed),
 * warnings (analysis will be limited), and informational items (enhance analysis).
 */
class EstateDataReadinessService
{
    /**
     * Assess the data readiness of a user for estate planning analysis.
     *
     * @param  User  $user  The user to assess
     * @return array{
     *     can_proceed: bool,
     *     blocking: array<int, array>,
     *     warnings: array<int, array>,
     *     info: array<int, array>,
     *     checks: array<int, array>,
     *     summary: string,
     *     completeness_percent: int
     * }
     */
    public function assess(User $user): array
    {
        $checks = $this->runAllChecks($user);

        $blocking = array_values(array_filter($checks, fn (array $check): bool => $check['level'] === 'blocking' && ! $check['passed']));
        $warnings = array_values(array_filter($checks, fn (array $check): bool => $check['level'] === 'warning' && ! $check['passed']));
        $info = array_values(array_filter($checks, fn (array $check): bool => $check['level'] === 'info' && ! $check['passed']));

        $canProceed = count($blocking) === 0;
        $passedCount = count(array_filter($checks, fn (array $check): bool => $check['passed']));
        $totalCount = count($checks);
        $completenessPercent = $totalCount > 0 ? (int) round(($passedCount / $totalCount) * 100) : 0;

        return [
            'can_proceed' => $canProceed,
            'blocking' => $blocking,
            'warnings' => $warnings,
            'info' => $info,
            'checks' => $checks,
            'summary' => $this->buildSummary($canProceed, $blocking, $warnings, $info),
            'completeness_percent' => $completenessPercent,
        ];
    }

    /**
     * Run all readiness checks and return the results.
     *
     * @return array<int, array{key: string, level: string, passed: bool, message: string, form_link: string}>
     */
    private function runAllChecks(User $user): array
    {
        return [
            $this->checkDateOfBirth($user),
            $this->checkMaritalStatus($user),
            $this->checkAtLeastOneAsset($user),
            $this->checkResidencyStatus($user),
            $this->checkPropertyData($user),
            $this->checkLiabilities($user),
            $this->checkFamilyMembers($user),
            $this->checkGiftsRecorded($user),
            $this->checkWillStatus($user),
            $this->checkIncomeData($user),
            $this->checkLifeInsurancePolicies($user),
            $this->checkPowerOfAttorney($user),
        ];
    }

    // ──────────────────────────────────────────────
    //  Blocking checks
    // ──────────────────────────────────────────────

    /**
     * Check that the user has a date of birth recorded.
     */
    private function checkDateOfBirth(User $user): array
    {
        return [
            'key' => 'date_of_birth',
            'level' => 'blocking',
            'passed' => $user->date_of_birth !== null,
            'message' => $user->date_of_birth !== null
                ? 'Date of birth is recorded.'
                : 'Date of birth is required for life expectancy and Inheritance Tax calculations.',
            'form_link' => '/profile/personal',
        ];
    }

    /**
     * Check that the user has a marital status recorded.
     *
     * Marital status is critical for spouse exemption calculations.
     */
    private function checkMaritalStatus(User $user): array
    {
        return [
            'key' => 'marital_status',
            'level' => 'blocking',
            'passed' => ! empty($user->marital_status),
            'message' => ! empty($user->marital_status)
                ? 'Marital status is recorded.'
                : 'Marital status is required to determine spouse exemption eligibility.',
            'form_link' => '/profile/personal',
        ];
    }

    /**
     * Check that the user has at least one asset recorded.
     *
     * Checks properties, investment accounts, savings accounts, and pensions.
     */
    private function checkAtLeastOneAsset(User $user): array
    {
        $hasAsset = $user->properties()->exists()
            || $user->investmentAccounts()->exists()
            || $user->savingsAccounts()->exists()
            || $user->dcPensions()->exists()
            || $user->dbPensions()->exists();

        return [
            'key' => 'at_least_one_asset',
            'level' => 'blocking',
            'passed' => $hasAsset,
            'message' => $hasAsset
                ? 'At least one asset is recorded.'
                : 'At least one asset (property, investment, savings, or pension) is required for estate analysis.',
            'form_link' => '/estate/assets',
        ];
    }

    // ──────────────────────────────────────────────
    //  Warning checks
    // ──────────────────────────────────────────────

    /**
     * Check that UK residency or domicile status is confirmed.
     */
    private function checkResidencyStatus(User $user): array
    {
        $passed = ! empty($user->domicile_status);

        return [
            'key' => 'residency_status',
            'level' => 'warning',
            'passed' => $passed,
            'message' => $passed
                ? 'UK residency status is confirmed.'
                : 'UK residency status is needed for accurate Inheritance Tax calculations.',
            'form_link' => '/profile/personal',
        ];
    }

    /**
     * Check that the user has at least one property with a current valuation.
     */
    private function checkPropertyData(User $user): array
    {
        $hasProperty = $user->properties()
            ->whereNotNull('current_value')
            ->where('current_value', '>', 0)
            ->exists();

        return [
            'key' => 'property_data',
            'level' => 'warning',
            'passed' => $hasProperty,
            'message' => $hasProperty
                ? 'Property data with current valuation is recorded.'
                : 'Property data with a current valuation improves Inheritance Tax and Residence Nil Rate Band calculations.',
            'form_link' => '/properties',
        ];
    }

    /**
     * Check that debts or mortgages have been recorded.
     */
    private function checkLiabilities(User $user): array
    {
        $hasLiabilities = $user->liabilities()->exists()
            || $user->mortgages()->exists();

        return [
            'key' => 'liabilities',
            'level' => 'warning',
            'passed' => $hasLiabilities,
            'message' => $hasLiabilities
                ? 'Liabilities and debts are recorded.'
                : 'Recording debts and mortgages ensures accurate net estate valuation.',
            'form_link' => '/properties',
        ];
    }

    /**
     * Check that family members (dependants or beneficiaries) are recorded.
     */
    private function checkFamilyMembers(User $user): array
    {
        $hasFamily = $user->familyMembers()->exists();

        return [
            'key' => 'family_members',
            'level' => 'warning',
            'passed' => $hasFamily,
            'message' => $hasFamily
                ? 'Family members are recorded.'
                : 'Recording dependants and beneficiaries enables personalised estate distribution analysis.',
            'form_link' => '/family',
        ];
    }

    /**
     * Check that gifts (Potentially Exempt Transfers or Chargeable Lifetime Transfers) are recorded.
     */
    private function checkGiftsRecorded(User $user): array
    {
        $hasGifts = Gift::where('user_id', $user->id)->exists();

        return [
            'key' => 'gifts_recorded',
            'level' => 'warning',
            'passed' => $hasGifts,
            'message' => $hasGifts
                ? 'Gifts and transfers are recorded.'
                : 'Recording lifetime gifts enables accurate Inheritance Tax calculations including taper relief.',
            'form_link' => '/estate/gifts',
        ];
    }

    /**
     * Check that will information has been recorded.
     */
    private function checkWillStatus(User $user): array
    {
        $hasWill = Will::where('user_id', $user->id)->exists();

        return [
            'key' => 'will_status',
            'level' => 'warning',
            'passed' => $hasWill,
            'message' => $hasWill
                ? 'Will information is recorded.'
                : 'Recording will details enables bequest analysis and charitable giving optimisation.',
            'form_link' => '/estate/planning',
        ];
    }

    // ──────────────────────────────────────────────
    //  Info checks
    // ──────────────────────────────────────────────

    /**
     * Check that gross annual income data is available.
     */
    private function checkIncomeData(User $user): array
    {
        $hasIncome = ($user->annual_employment_income ?? 0) > 0
            || ($user->annual_self_employment_income ?? 0) > 0
            || ($user->annual_rental_income ?? 0) > 0
            || ($user->annual_dividend_income ?? 0) > 0
            || ($user->annual_interest_income ?? 0) > 0
            || ($user->annual_other_income ?? 0) > 0
            || ($user->annual_trust_income ?? 0) > 0;

        return [
            'key' => 'income_data',
            'level' => 'info',
            'passed' => $hasIncome,
            'message' => $hasIncome
                ? 'Income data is available.'
                : 'Adding income data enables gifting affordability analysis and cash flow projections.',
            'form_link' => '/profile/employment',
        ];
    }

    /**
     * Check that life insurance policies are recorded.
     */
    private function checkLifeInsurancePolicies(User $user): array
    {
        $hasPolicies = $user->lifeInsurancePolicies()->exists();

        return [
            'key' => 'life_insurance_policies',
            'level' => 'info',
            'passed' => $hasPolicies,
            'message' => $hasPolicies
                ? 'Life insurance policies are recorded.'
                : 'Recording life insurance policies enables Inheritance Tax liability cover analysis.',
            'form_link' => '/protection/policies',
        ];
    }

    /**
     * Check that Lasting Power of Attorney information is recorded.
     *
     * Checks for dedicated LPA records first, then falls back to
     * Letter to Spouse attorney details.
     */
    private function checkPowerOfAttorney(User $user): array
    {
        $hasLpa = LastingPowerOfAttorney::where('user_id', $user->id)->exists();

        if ($hasLpa) {
            $registeredCount = LastingPowerOfAttorney::where('user_id', $user->id)
                ->where('status', 'registered')
                ->count();

            return [
                'key' => 'power_of_attorney',
                'level' => 'warning',
                'passed' => true,
                'message' => $registeredCount > 0
                    ? 'Lasting Power of Attorney is registered with the Office of the Public Guardian.'
                    : 'Lasting Power of Attorney has been created but is not yet registered. Registration with the Office of the Public Guardian is required before it can be used.',
                'form_link' => '/estate',
            ];
        }

        // Fall back to Letter to Spouse attorney details
        $letter = LetterToSpouse::where('user_id', $user->id)->first();
        $hasAttorney = $letter !== null
            && (! empty($letter->attorney_name) || ! empty($letter->attorney_contact));

        return [
            'key' => 'power_of_attorney',
            'level' => 'warning',
            'passed' => $hasAttorney,
            'message' => $hasAttorney
                ? 'Basic attorney contact information is recorded. Consider creating a formal Lasting Power of Attorney.'
                : 'Creating a Lasting Power of Attorney ensures your estate plan covers incapacity planning. Without one, your family may need to apply to the Court of Protection.',
            'form_link' => '/estate',
        ];
    }

    // ──────────────────────────────────────────────
    //  Summary
    // ──────────────────────────────────────────────

    /**
     * Build a human-readable summary of the readiness assessment.
     *
     * @param  bool  $canProceed  Whether analysis can proceed
     * @param  array<int, array>  $blocking  Failing blocking checks
     * @param  array<int, array>  $warnings  Failing warning checks
     * @param  array<int, array>  $info  Failing info checks
     */
    private function buildSummary(bool $canProceed, array $blocking, array $warnings, array $info): string
    {
        if (! $canProceed) {
            $count = count($blocking);

            return $count === 1
                ? 'Estate planning analysis cannot proceed — 1 required item is missing.'
                : "Estate planning analysis cannot proceed — {$count} required items are missing.";
        }

        if (count($warnings) > 0) {
            $count = count($warnings);

            return $count === 1
                ? 'Estate planning analysis can proceed, but 1 item would improve accuracy.'
                : "Estate planning analysis can proceed, but {$count} items would improve accuracy.";
        }

        if (count($info) > 0) {
            $count = count($info);

            return $count === 1
                ? 'Estate planning analysis is ready. 1 optional item could enhance your results.'
                : "Estate planning analysis is ready. {$count} optional items could enhance your results.";
        }

        return 'Estate planning analysis is fully ready — all data has been recorded.';
    }
}

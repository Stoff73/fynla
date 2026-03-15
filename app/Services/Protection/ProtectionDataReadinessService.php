<?php

declare(strict_types=1);

namespace App\Services\Protection;

use App\Models\LifeEvent;
use App\Models\User;

class ProtectionDataReadinessService
{
    /**
     * Readiness levels that determine whether analysis can proceed.
     */
    private const LEVEL_BLOCKING = 'blocking';

    private const LEVEL_WARNING = 'warning';

    private const LEVEL_INFO = 'info';

    /**
     * Assess data readiness for the Protection module.
     *
     * Returns a structured array indicating whether analysis can proceed,
     * along with categorised messages for any missing or incomplete data.
     *
     * @return array{
     *     can_proceed: bool,
     *     blocking: array<int, array{key: string, level: string, passed: bool, message: string, form_link: string}>,
     *     warnings: array<int, array{key: string, level: string, passed: bool, message: string, form_link: string}>,
     *     info: array<int, array{key: string, level: string, passed: bool, message: string, form_link: string}>,
     *     total_checks: int,
     *     passed_checks: int,
     *     completeness_percent: int,
     * }
     */
    public function assess(User $user): array
    {
        $protectionProfile = $user->relationLoaded('protectionProfile')
            ? $user->protectionProfile
            : $user->protectionProfile()->first();

        $checks = [
            ...$this->blockingChecks($user),
            ...$this->warningChecks($user, $protectionProfile),
            ...$this->infoChecks($user, $protectionProfile),
        ];

        $blocking = array_values(array_filter($checks, fn (array $c) => $c['level'] === self::LEVEL_BLOCKING && ! $c['passed']));
        $warnings = array_values(array_filter($checks, fn (array $c) => $c['level'] === self::LEVEL_WARNING && ! $c['passed']));
        $info = array_values(array_filter($checks, fn (array $c) => $c['level'] === self::LEVEL_INFO && ! $c['passed']));

        $passedCount = count(array_filter($checks, fn (array $c) => $c['passed']));
        $totalChecks = count($checks);

        return [
            'can_proceed' => count($blocking) === 0,
            'blocking' => $blocking,
            'warnings' => $warnings,
            'info' => $info,
            'total_checks' => $totalChecks,
            'passed_checks' => $passedCount,
            'completeness_percent' => $totalChecks > 0
                ? (int) round(($passedCount / $totalChecks) * 100)
                : 0,
        ];
    }

    /**
     * Blocking checks — analysis cannot proceed without these.
     *
     * @return array<int, array{key: string, level: string, passed: bool, message: string, form_link: string}>
     */
    private function blockingChecks(User $user): array
    {
        return [
            $this->check(
                key: 'date_of_birth',
                level: self::LEVEL_BLOCKING,
                passed: $user->date_of_birth !== null,
                message: 'Date of birth is required to calculate protection needs and policy terms.',
                formLink: '/profile/personal',
            ),
            $this->check(
                key: 'income',
                level: self::LEVEL_BLOCKING,
                passed: $this->hasIncome($user),
                message: 'Gross annual income is required to assess income replacement and coverage amounts.',
                formLink: '/profile/employment',
            ),
            $this->check(
                key: 'marital_status',
                level: self::LEVEL_BLOCKING,
                passed: $user->marital_status !== null,
                message: 'Marital status is required to determine household protection needs.',
                formLink: '/profile/personal',
            ),
        ];
    }

    /**
     * Warning checks — analysis will be limited without these.
     *
     * @return array<int, array{key: string, level: string, passed: bool, message: string, form_link: string}>
     */
    private function warningChecks(User $user, mixed $protectionProfile): array
    {
        return [
            $this->check(
                key: 'expenditure',
                level: self::LEVEL_WARNING,
                passed: $this->hasExpenditure($user),
                message: 'Monthly expenditure helps calculate the income your household would need if you were unable to work.',
                formLink: '/profile/expenditure',
            ),
            $this->check(
                key: 'employment_status',
                level: self::LEVEL_WARNING,
                passed: $user->employment_status !== null,
                message: 'Employment status determines which protection products are most suitable for your situation.',
                formLink: '/profile/employment',
            ),
            $this->check(
                key: 'dependant_ages',
                level: self::LEVEL_WARNING,
                passed: $this->hasDependants($user, $protectionProfile),
                message: 'Recording children or dependant ages helps calculate how long protection cover is needed.',
                formLink: '/family',
            ),
            $this->check(
                key: 'existing_policies',
                level: self::LEVEL_WARNING,
                passed: $this->hasExistingPolicies($user, $protectionProfile),
                message: 'Adding your existing protection policies allows us to identify gaps in your current cover.',
                formLink: '/protection/policies',
            ),
            $this->check(
                key: 'employer_benefits',
                level: self::LEVEL_WARNING,
                passed: $this->hasEmployerBenefits($protectionProfile),
                message: 'Employer benefits such as death in service and group income protection can significantly reduce the cover you need to arrange privately.',
                formLink: '/protection/employer-benefits',
            ),
            $this->check(
                key: 'debts_and_liabilities',
                level: self::LEVEL_WARNING,
                passed: $this->hasDebtsRecorded($user),
                message: 'Outstanding debts affect the capital sum your family would need if you were to die or become incapacitated.',
                formLink: '/profile/liabilities',
            ),
        ];
    }

    /**
     * Info checks — these enhance the quality of analysis.
     *
     * @return array<int, array{key: string, level: string, passed: bool, message: string, form_link: string}>
     */
    private function infoChecks(User $user, mixed $protectionProfile): array
    {
        return [
            $this->check(
                key: 'occupation',
                level: self::LEVEL_INFO,
                passed: $this->hasOccupation($user, $protectionProfile),
                message: 'Occupation details help estimate income protection premiums and eligibility.',
                formLink: '/profile/employment',
            ),
            $this->check(
                key: 'smoker_status',
                level: self::LEVEL_INFO,
                passed: $this->hasSmokerStatus($user, $protectionProfile),
                message: 'Smoking status is a key factor in protection policy pricing.',
                formLink: '/protection',
            ),
            $this->check(
                key: 'health_conditions',
                level: self::LEVEL_INFO,
                passed: $this->hasHealthDetails($user),
                message: 'Health information helps refine premium estimates and highlight conditions that may affect cover.',
                formLink: '/protection',
            ),
            $this->check(
                key: 'spouse_income',
                level: self::LEVEL_INFO,
                passed: $this->hasSpouseIncome($user),
                message: "Your partner's income is used for joint analysis and to assess household financial resilience.",
                formLink: '/profile/personal',
            ),
            $this->check(
                key: 'life_events',
                level: self::LEVEL_INFO,
                passed: $this->hasLifeEvents($user),
                message: 'Life events such as expected inheritances, large purchases, or career changes can affect your protection needs.',
                formLink: '/goals/life-events',
            ),
        ];
    }

    /**
     * Build a single check result.
     *
     * @return array{key: string, level: string, passed: bool, message: string, form_link: string}
     */
    private function check(string $key, string $level, bool $passed, string $message, string $formLink): array
    {
        return [
            'key' => $key,
            'level' => $level,
            'passed' => $passed,
            'message' => $message,
            'form_link' => $formLink,
        ];
    }

    // ------------------------------------------------------------------
    //  Individual field checks
    // ------------------------------------------------------------------

    /**
     * User has at least one source of gross annual income greater than zero.
     */
    private function hasIncome(User $user): bool
    {
        return ($user->annual_employment_income ?? 0) > 0
            || ($user->annual_self_employment_income ?? 0) > 0
            || ($user->annual_rental_income ?? 0) > 0
            || ($user->annual_dividend_income ?? 0) > 0
            || ($user->annual_other_income ?? 0) > 0
            || ($user->annual_trust_income ?? 0) > 0;
    }

    /**
     * User has recorded monthly or annual expenditure.
     */
    private function hasExpenditure(User $user): bool
    {
        return ($user->monthly_expenditure ?? 0) > 0
            || ($user->annual_expenditure ?? 0) > 0;
    }

    /**
     * User has dependants recorded — either via family members or the protection profile.
     */
    private function hasDependants(User $user, mixed $protectionProfile): bool
    {
        // Check protection profile for dependants
        if ($protectionProfile && ($protectionProfile->number_of_dependents ?? 0) > 0) {
            return true;
        }

        // Check family members marked as dependent
        $familyMembers = $user->relationLoaded('familyMembers')
            ? $user->familyMembers
            : $user->familyMembers()->get();

        return $familyMembers->where('is_dependent', true)->isNotEmpty();
    }

    /**
     * User has existing protection policies or has explicitly confirmed they have none.
     */
    private function hasExistingPolicies(User $user, mixed $protectionProfile): bool
    {
        // Explicitly declared no policies
        if ($protectionProfile && $protectionProfile->has_no_policies) {
            return true;
        }

        // Check for any protection policy type
        $hasLifeInsurance = $user->relationLoaded('lifeInsurancePolicies')
            ? $user->lifeInsurancePolicies->isNotEmpty()
            : $user->lifeInsurancePolicies()->exists();

        if ($hasLifeInsurance) {
            return true;
        }

        $hasCriticalIllness = $user->relationLoaded('criticalIllnessPolicies')
            ? $user->criticalIllnessPolicies->isNotEmpty()
            : $user->criticalIllnessPolicies()->exists();

        if ($hasCriticalIllness) {
            return true;
        }

        $hasIncomeProtection = $user->relationLoaded('incomeProtectionPolicies')
            ? $user->incomeProtectionPolicies->isNotEmpty()
            : $user->incomeProtectionPolicies()->exists();

        if ($hasIncomeProtection) {
            return true;
        }

        $hasDisability = $user->relationLoaded('disabilityPolicies')
            ? $user->disabilityPolicies->isNotEmpty()
            : $user->disabilityPolicies()->exists();

        if ($hasDisability) {
            return true;
        }

        $hasSicknessIllness = $user->relationLoaded('sicknessIllnessPolicies')
            ? $user->sicknessIllnessPolicies->isNotEmpty()
            : $user->sicknessIllnessPolicies()->exists();

        return $hasSicknessIllness;
    }

    /**
     * User has recorded employer benefits (Sprint 2 columns on protection_profiles).
     */
    private function hasEmployerBenefits(mixed $protectionProfile): bool
    {
        if (! $protectionProfile) {
            return false;
        }

        return $protectionProfile->death_in_service_multiple !== null
            || $protectionProfile->group_ip_benefit_percent !== null
            || $protectionProfile->group_ci_amount !== null
            || $protectionProfile->has_employer_pmi !== null;
    }

    /**
     * User has recorded debts/liabilities (Estate module liabilities or reviewed flag).
     */
    private function hasDebtsRecorded(User $user): bool
    {
        // User has explicitly confirmed they have reviewed liabilities (even if zero)
        if ($user->liabilities_reviewed) {
            return true;
        }

        // Check for recorded liabilities
        $hasLiabilities = $user->relationLoaded('liabilities')
            ? $user->liabilities->isNotEmpty()
            : $user->liabilities()->exists();

        // Check for mortgages as well
        if (! $hasLiabilities) {
            $hasLiabilities = $user->relationLoaded('mortgages')
                ? $user->mortgages->isNotEmpty()
                : $user->mortgages()->exists();
        }

        return $hasLiabilities;
    }

    /**
     * User has occupation details on their profile or protection profile.
     */
    private function hasOccupation(User $user, mixed $protectionProfile): bool
    {
        return ($user->occupation !== null && $user->occupation !== '')
            || ($protectionProfile && $protectionProfile->occupation !== null && $protectionProfile->occupation !== '');
    }

    /**
     * Smoker status is known via the user profile or protection profile.
     */
    private function hasSmokerStatus(User $user, mixed $protectionProfile): bool
    {
        // User table has smoking_status with default 'never', so check if explicitly set
        // Protection profile has smoker_status boolean
        if ($protectionProfile && $protectionProfile->smoker_status !== null) {
            return true;
        }

        // The user table smoking_status defaults to 'never' — we treat it as set
        // since the user must have actively chosen a value during onboarding
        return $user->smoking_status !== null;
    }

    /**
     * User has provided health condition information.
     */
    private function hasHealthDetails(User $user): bool
    {
        // health_status on users table: 'yes' means healthy, other values indicate conditions
        // We consider any non-null value as "health details available"
        return $user->health_status !== null;
    }

    /**
     * Spouse has income recorded (for joint household analysis).
     */
    private function hasSpouseIncome(User $user): bool
    {
        // Only relevant for married/partnered users
        if (! in_array($user->marital_status, ['married'], true)) {
            // Not married — this check is not applicable, so mark as passed
            return true;
        }

        if (! $user->spouse_id) {
            return false;
        }

        $spouse = $user->relationLoaded('spouse')
            ? $user->spouse
            : $user->spouse()->first();

        if (! $spouse) {
            return false;
        }

        return ($spouse->annual_employment_income ?? 0) > 0
            || ($spouse->annual_self_employment_income ?? 0) > 0
            || ($spouse->annual_rental_income ?? 0) > 0
            || ($spouse->annual_dividend_income ?? 0) > 0
            || ($spouse->annual_other_income ?? 0) > 0
            || ($spouse->annual_trust_income ?? 0) > 0;
    }

    /**
     * User has active life events that could affect protection planning.
     */
    private function hasLifeEvents(User $user): bool
    {
        return LifeEvent::where('user_id', $user->id)
            ->active()
            ->exists();
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\DCPension;

/**
 * The two rules for reading a defined contribution pension's employee
 * contribution — the one home for both (W-0424).
 *
 * They lived as private statics on `UserProfileService`, which is a profile
 * service reading a pension model, and the Store boundary said so: it was the only
 * reason that service imported `App\Models\DCPension` at all, and the suite had
 * been red on it. Neither rule is about a profile and neither queries anything —
 * both read properties off an instance the caller already fetched through
 * `PensionStore` — so they belong in the pension domain, where the next person
 * looking for "how much does this pension take each month" will look.
 *
 * No queries and no mutations here, deliberately. This class takes a pension it is
 * handed and answers a question about it.
 */
final class PensionContributionRule
{
    /**
     * What this pension takes from the member each month — the ONE answer.
     *
     * **W-0424.** Two mechanisms used to answer this and neither reached the
     * other's records. The tax side read `employee_contribution_percent ×
     * annual_salary`; `getFinancialCommitments()` read
     * `monthly_contribution_amount` and gated on it being greater than zero. A
     * member recording 8% of £145,000 with a null monthly amount was therefore
     * counted by neither: £11,600 a year left their pay and **nothing in the
     * application deducted it from what they had available to spend.**
     *
     * The explicit amount wins where it is set, because it is what the member
     * actually told us. The percentage is the fallback, not the other way round.
     */
    public static function monthlyEmployee(DCPension $pension, float $fallbackSalary = 0.0): float
    {
        $stated = (float) ($pension->monthly_contribution_amount ?? 0);

        if ($stated > 0) {
            return $stated;
        }

        $percent = (float) ($pension->employee_contribution_percent ?? 0);
        // The onboarding form captures the percentage but not the scheme's
        // salary; the caller's own pay figure stands in for it.
        $salary = (float) ($pension->annual_salary ?? 0) ?: $fallbackSalary;

        if ($percent <= 0 || $salary <= 0) {
            return 0.0;
        }

        return ($salary * $percent / 100) / 12;
    }

    /**
     * What the employer pays in each month: their percentage of the scheme
     * salary, the member's employment income standing in when the onboarding
     * form left the scheme salary blank.
     */
    public static function monthlyEmployer(DCPension $pension, float $fallbackSalary = 0.0): float
    {
        $percent = (float) ($pension->employer_contribution_percent ?? 0);
        $salary = (float) ($pension->annual_salary ?? 0) ?: $fallbackSalary;

        return $percent > 0 && $salary > 0 ? ($salary * $percent / 100) / 12 : 0.0;
    }

    /**
     * What reaches the pot each month: the member's contribution plus the
     * employer's. A personal pension or SIPP is relief at source (FA 2004 s192):
     * the member pays net and the provider claims basic-rate relief on top, so
     * the pot receives the net payment ÷ (1 − basic rate). A workplace scheme is
     * net pay (s193(2)), paid gross already.
     * https://www.legislation.gov.uk/ukpga/2004/12/section/192
     */
    public static function monthlyIntoPot(DCPension $pension, float $fallbackSalary, float $basicRelief): float
    {
        $employee = self::monthlyEmployee($pension, $fallbackSalary);
        if (! self::isWorkplace($pension) && $basicRelief > 0 && $basicRelief < 1) {
            $employee /= (1 - $basicRelief);
        }

        return $employee + self::monthlyEmployer($pension, $fallbackSalary);
    }

    /**
     * Whether this is an employer-run scheme, the only kind salary sacrifice
     * can apply to. `scheme_type` decides when it is set. The onboarding form
     * records the kind in `pension_type` and leaves `scheme_type` null, so
     * 'occupational' there counts as workplace too.
     */
    public static function isWorkplace(DCPension $pension): bool
    {
        if ((string) ($pension->scheme_type ?? '') !== '') {
            return $pension->scheme_type === 'workplace';
        }

        return $pension->pension_type === 'occupational';
    }
}

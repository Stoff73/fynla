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
    public static function monthlyEmployee(DCPension $pension): float
    {
        $stated = (float) ($pension->monthly_contribution_amount ?? 0);

        if ($stated > 0) {
            return $stated;
        }

        $percent = (float) ($pension->employee_contribution_percent ?? 0);
        $salary = (float) ($pension->annual_salary ?? 0);

        if ($percent <= 0 || $salary <= 0) {
            return 0.0;
        }

        return ($salary * $percent / 100) / 12;
    }

    /**
     * Whether this pension's contribution comes out of pay.
     *
     * **W-0424, and a second fault found while fixing the first.** The old test
     * was `in_array($pension->scheme_type, ['workplace', 'occupational',
     * 'auto_enrolment'])` — but the column is
     * `enum('workplace','sipp','personal')`, so **two of the three permitted
     * values could never match**, and the live data also carries NULL. David's
     * workplace pension has a null `scheme_type`, so the tax side returned £0 for
     * an 8%-of-£145,000 record.
     *
     * Stated as an EXCLUSION rather than an allowlist for that reason: a SIPP or
     * a personal pension is funded by the member from money they have already
     * received, so it is not a salary deduction. Anything else with a salary and
     * a percentage on it is one by construction — a personal pension has no
     * employer salary basis to compute against.
     */
    public static function isSalaryDeducted(DCPension $pension): bool
    {
        return ! in_array($pension->scheme_type, ['sipp', 'personal'], true);
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * The policy dates every protection policy carries.
 *
 * `BasePolicyRequest::commonRules()` validates `policy_start_date` and
 * `policy_end_date` for all five policy types, and all five tables have both
 * columns. Before this trait each model repeated the pair in `$fillable` and
 * `$casts`, and four of the five had left `policy_end_date` out — so a date the
 * user typed was validated, accepted, discarded by mass assignment, and still
 * answered 201 (W-0026). One declaration here, read by all five models.
 *
 * `policy_term_years` is deliberately NOT here: `income_protection_policies`
 * has no such column, so it stays on the four models whose tables carry it.
 */
trait RecordsPolicyDates
{
    /** @var list<string> */
    public static array $policyDateFields = ['policy_start_date', 'policy_end_date'];

    public function initializeRecordsPolicyDates(): void
    {
        $this->mergeFillable(self::$policyDateFields);
        $this->mergeCasts(array_fill_keys(self::$policyDateFields, 'date'));
    }
}

<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Per-entity-type allowlist of fields the AI may update via the
 * `update_record` tool. The previous implementation used a 2-field
 * blocklist (`user_id`, `id`), which left every other fillable column
 * — including identity FKs, audit timestamps, and security-sensitive
 * fields like Trust.settlor or FamilyMember.relationship — wide open
 * to the LLM. This positive-list approach inverts that.
 *
 * Field names match the actual model columns (post field-aliasing in
 * the handler — e.g. the LLM may send `monthly_premium` but the
 * handler aliases it to `premium_amount` before consulting this list).
 *
 * Forbidden by design (must never appear in any branch):
 * - identity FKs: user_id, joint_owner_id, household_id, trust_id,
 *   linked_user_id, holdable_id
 * - audit timestamps: created_at, updated_at, deleted_at, valuation_date
 *   (kept for some entities where it carries domain meaning, not audit)
 * - relationship/settlor/donor fields that change ownership semantics
 *   (Trust.settlor, FamilyMember.relationship, Will.testator_id,
 *    LastingPowerOfAttorney.donor_id)
 * - Mortgage.start_date / mortgage_type (rewriting these breaks the
 *   contractual boundaries of the loan, not a routine edit)
 * - National Insurance number / sensitive PII (already excluded from
 *   FamilyMember $fillable)
 */
final class UpdateRecordAllowlist
{
    /**
     * @var array<string, list<string>>
     */
    public const MAP = [
        'goal' => [
            'goal_name', 'target_amount', 'target_date', 'priority', 'status',
            'monthly_contribution', 'description', 'is_essential',
        ],
        'life_event' => [
            'event_name', 'event_type', 'expected_date', 'amount', 'certainty',
            'description', 'impact_type',
        ],
        'savings_account' => [
            'account_name', 'account_type', 'institution', 'current_balance',
            'interest_rate', 'access_type', 'isa_subscription_amount',
            'regular_contribution_amount', 'contribution_frequency',
        ],
        'investment_account' => [
            'account_name', 'account_type', 'provider', 'current_value',
            'monthly_contribution_amount', 'contributions_ytd',
        ],
        'dc_pension' => [
            'scheme_name', 'provider', 'current_fund_value',
            'monthly_contribution_amount', 'employee_contribution_percent',
            'employer_contribution_percent', 'retirement_age',
            // BUG-02 (2026-08-17): pension_type was absent, so a scheme type
            // supplied AFTER the record was created had nowhere to land —
            // update_record dropped it as disallowed. The user answered "Sip" to
            // Fyn's own question and the pension stayed a workplace pension while
            // Fyn reported "Recorded — Self-Invested Personal Pension". A
            // classification, not an identity or ownership field, so it is safe
            // to correct.
            'pension_type',
            // Campaign-captured contribution fields: salary basis + salary
            // sacrifice (occupational scheme) and the flexible-access flag
            // (campaign2_flexible_access — closes the round-1 latent item where
            // the "yes" branch could not persist has_flexibly_accessed).
            'annual_salary', 'salary_sacrifice', 'has_flexibly_accessed',
        ],
        'db_pension' => [
            'scheme_name', 'accrued_annual_pension', 'normal_retirement_age',
            'pensionable_salary', 'pensionable_service_years',
            // W-0017: the scheme type, the spouse's continuing pension, the
            // inflation protection and the tax-free lump sum were all
            // uncorrectable through Fyn — the only entry route /m and native
            // have — even though PensionStore validates and writes every one.
            'scheme_type', 'spouse_pension_percent', 'inflation_protection',
            'revaluation_method', 'lump_sum_entitlement',
            // W-0032: the scheme status decides whether the pension counts as
            // income today. Leaving it off here would mean /m and native users —
            // whose only entry route is Fyn — could state it once and never
            // correct it, which is the gap W-0017 closed for the four above.
            'scheme_status',
        ],
        'property' => [
            'current_value', 'property_type', 'address_line_1',
            'monthly_rental_income',
        ],
        'mortgage' => [
            'lender_name', 'outstanding_balance', 'interest_rate',
            'monthly_payment', 'remaining_term_months',
        ],
        'life_insurance' => [
            'provider', 'sum_assured', 'premium_amount', 'premium_frequency',
            'policy_end_date',
        ],
        'critical_illness' => [
            'provider', 'sum_assured', 'premium_amount', 'premium_frequency',
            // W-0026: life_insurance already allowed the end date; these two did
            // not, and the create tool schema has never carried one. Fyn is the
            // only entry route /m and native have, so the expiry date of a
            // critical illness or income protection policy could not be recorded
            // there at all. The end date drives coverage-expiry modelling.
            'policy_end_date',
        ],
        'income_protection' => [
            'provider', 'benefit_amount', 'premium_amount', 'premium_frequency',
            'deferred_period_weeks', 'policy_end_date',
        ],
        'estate_asset' => [
            'asset_name', 'asset_type', 'current_value',
        ],
        'estate_liability' => [
            'liability_name', 'liability_type', 'current_balance',
            'monthly_payment', 'interest_rate',
        ],
        'estate_gift' => [
            'gift_value', 'gift_date', 'recipient', 'gift_type',
        ],
        'family_member' => [
            'first_name', 'last_name', 'date_of_birth', 'annual_income',
            'gender', 'is_dependent', 'education_status',
        ],
        'trust' => [
            'trust_name', 'trust_type', 'current_value', 'initial_value',
        ],
        'business_interest' => [
            'business_name', 'ownership_percentage', 'current_valuation',
            'annual_revenue', 'annual_profit', 'company_number',
        ],
        'chattel' => [
            'name', 'description', 'current_value', 'chattel_type',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function allowedFields(string $entityType): array
    {
        return self::MAP[$entityType] ?? [];
    }

    /**
     * @return list<string>
     */
    public static function entityTypes(): array
    {
        return array_keys(self::MAP);
    }

    /**
     * Aggregate union of every allowed field across all entity types.
     * Used by the schema layer so the LLM cannot invent field names.
     * Per-entity allowlist is enforced at runtime in the handler.
     *
     * @return list<string>
     */
    public static function allFieldNames(): array
    {
        $all = [];
        foreach (self::MAP as $fields) {
            foreach ($fields as $field) {
                $all[$field] = true;
            }
        }

        return array_keys($all);
    }
}

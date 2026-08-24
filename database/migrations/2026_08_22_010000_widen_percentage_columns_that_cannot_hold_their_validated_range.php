<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * W-0263 — validation rules that permit values their column cannot physically store.
 *
 * `decimal(5,4)` is five significant digits with four after the point: it stops at
 * **9.9999**. That is the correct type for a *fraction* (0.05 meaning 5%), and this
 * schema does use it that way — `life_insurance_policies.decreasing_rate` and
 * `dc_pensions.employer_ni_rebate_pct` are genuine fractions, validated `max:1`.
 *
 * The columns below are not fractions. Every one of them stores a **percentage**,
 * verified against live rows before this migration was written: mortgage rates are
 * `4.5000` meaning 4.5%, savings rates `4.2500`, platform fees `0.4500`, adviser
 * fees `0.7500`. Their validation rules say so too — `max:100` on a mortgage rate,
 * `max:20` on a savings rate. So the rule was right and the column was wrong, and
 * the user paid for the disagreement with `SQLSTATE[22003] Out of range` — a 500,
 * not a validation message.
 *
 * The precedent for the target types is inside the same schema: `mortgages`
 * already stores its headline `interest_rate` as `decimal(8,4)`, and
 * `liabilities.interest_rate` matches it. The two portion-rates on `mortgages`
 * were simply built narrower than their sibling.
 *
 * Rates go to `decimal(8,4)` to match that precedent. Percentages that are bounded
 * at 100 by their nature (ownership, fees) go to `decimal(7,4)`, which holds
 * 0–999.9999 and so cannot be the binding constraint on any percentage.
 *
 * Raw `MODIFY COLUMN` rather than `->change()`: doctrine/dbal is not installed,
 * and `->change()` silently drops any attribute not restated — several of these
 * columns are NOT NULL with a default, and losing that would be a worse defect
 * than the one being fixed.
 *
 * Reversibility: `down()` restores the original definitions exactly. It is only
 * safe while no row holds a value the narrower column cannot store — which is the
 * whole point of the widening, so a populated database may legitimately refuse to
 * reverse it. That is stated rather than worked around.
 */
return new class extends Migration
{
    /**
     * [table, column, widened definition, original definition].
     *
     * Definitions are spelled out in full so nullability and defaults survive the
     * change; they were read from `information_schema` rather than from memory.
     */
    private const COLUMNS = [
        // Mortgage portion rates — percentages, e.g. 4.5 meaning 4.5%. The rule
        // says max:100 and the form input says max="100" with placeholder "e.g.,
        // 3.5". Any rate of 10% or more 500s today: most of British history, and
        // current adverse-credit and some buy-to-let products.
        //
        // The old column comment read "annual rate as decimal", which is stale
        // prose from the original migration — nothing in the code has ever treated
        // these as fractions. `MortgageNormaliser` rounds them exactly as it
        // rounds `interest_rate`, and `PropertyDetailInline.vue` renders them with
        // a `%` suffix and no conversion. The comment is corrected here so the
        // next reader is not sent down the same blind alley.
        ['mortgages', 'fixed_interest_rate',
            "decimal(8,4) NULL DEFAULT NULL COMMENT 'Annual interest rate for the fixed portion, as a percentage (4.5 = 4.5%)'",
            "decimal(5,4) NULL DEFAULT NULL COMMENT 'Interest rate for fixed portion (annual rate as decimal)'"],
        ['mortgages', 'variable_interest_rate',
            "decimal(8,4) NULL DEFAULT NULL COMMENT 'Annual interest rate for the variable portion, as a percentage (5.19 = 5.19%)'",
            "decimal(5,4) NULL DEFAULT NULL COMMENT 'Interest rate for variable portion (annual rate as decimal)'"],

        // Savings rate — the rule advertises 20 and its own message says
        // "cannot exceed 20%", while the column stopped below 10.
        ['savings_accounts', 'interest_rate',
            'decimal(8,4) NOT NULL DEFAULT 0.0000',
            'decimal(5,4) NOT NULL DEFAULT 0.0000'],

        // A percentage stake in a private company. The input is
        // `min="0" max="100" step="0.01"` and the detail view renders it with a
        // `%` suffix — so a 50% shareholding could not be recorded at all, and the
        // table holds zero non-null rows, which is what that looks like from the
        // outside.
        ['investment_accounts', 'current_ownership_percent',
            'decimal(7,4) NULL DEFAULT NULL',
            'decimal(5,4) NULL DEFAULT NULL'],

        // Fee percentages. `platform_fee_percent` had no `max:` rule at all, so
        // the column was the only thing standing between the user and a 500;
        // `advisor_fee_percent` had `max:10`, where exactly 10 overflowed.
        ['investment_accounts', 'platform_fee_percent',
            'decimal(7,4) NULL DEFAULT 0.0000',
            'decimal(5,4) NULL DEFAULT 0.0000'],
        ['investment_accounts', 'advisor_fee_percent',
            'decimal(7,4) NOT NULL DEFAULT 0.0000',
            'decimal(5,4) NOT NULL DEFAULT 0.0000'],
        ['dc_pensions', 'platform_fee_percent',
            'decimal(7,4) NULL DEFAULT NULL',
            'decimal(5,4) NULL DEFAULT NULL'],
        ['dc_pensions', 'advisor_fee_percent',
            'decimal(7,4) NULL DEFAULT NULL',
            'decimal(5,4) NULL DEFAULT NULL'],

        // Admin reference data: the rule permits 120 years, the column stopped at
        // 99.99. Low reach, but the same disagreement.
        ['actuarial_life_tables', 'life_expectancy_years',
            'decimal(5,2) NOT NULL',
            'decimal(4,2) NOT NULL'],

        // A trust loan rate. Validated `nullable|numeric|min:0` with no upper
        // bound at all, behind a form labelled "Interest Rate (%)", so the column
        // was the only limit and a 10% loan was a 500.
        ['trusts', 'loan_interest_rate',
            'decimal(8,4) NULL DEFAULT NULL',
            'decimal(5,4) NULL DEFAULT NULL'],

        // The two columns W-0261 stopped the bleeding on. Its rules were capped at
        // `max:9.9999` explicitly as a half-fix, with the comment at
        // `StoreHoldingRequest` recording that "a real dividend yield CAN exceed
        // 10%, so the column is too narrow ... W-0263 owns the decision and this
        // line is the stop-the-bleeding half". This is W-0263 taking it: a
        // double-digit yield is ordinary on investment trusts and distressed
        // REITs, so the columns are widened and the rules restored to the honest
        // range rather than left capped at a bound no user could work around.
        ['holdings', 'dividend_yield',
            'decimal(7,4) NOT NULL DEFAULT 0.0000',
            'decimal(5,4) NOT NULL DEFAULT 0.0000'],
        ['holdings', 'ocf_percent',
            'decimal(7,4) NOT NULL DEFAULT 0.0000',
            'decimal(5,4) NOT NULL DEFAULT 0.0000'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as [$table, $column, $widened]) {
            DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$widened}");
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as [$table, $column, , $original]) {
            DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$original}");
        }
    }
};

<?php

declare(strict_types=1);

namespace App\Services\Actions;

/**
 * Which tax actions close at the tax year end, save once, or move money. The
 * card's words ("Why this matters for you", the steps, "What this changes")
 * live in database/seeders/data/action-how-to/tax.md (ActionHowTo), filled
 * with the user's own figures (CSJ 2026-09-28).
 */
final class ActionCardFigures
{
    /**
     * Allowances that are set per tax year, so the card shows the tax-year end
     * as its deadline. ISA: https://www.gov.uk/individual-savings-accounts/how-isas-work;
     * pension annual allowance: https://www.gov.uk/tax-on-your-private-pension/annual-allowance;
     * dividend allowance: https://www.gov.uk/tax-on-dividends.
     */
    public const ANNUAL_ALLOWANCE_TYPES = [
        'isa_topup_vs_psa', 'isa_topup_spouse', 'bed_and_isa', 'junior_isa', 'lifetime_isa',
        'pension_tax_relief', 'pension_aa_carry_forward', 'non_earner_spouse_pension',
        'dividend_allowance_harvest',
        // Relief goes to the tax year the contribution is paid in (FA 2004 s188).
        'pa_taper_rescue', 'additional_rate_avoidance',
    ];

    /** Savings made once, not every year: a key figure never says "a year". */
    public const ONE_OFF_TYPES = ['pension_aa_carry_forward', 'bed_and_isa'];

    /**
     * Tax actions that move the user's money, so the card offers "Fund from"
     * (design C; CSJ 2026-09-26).
     */
    public const FUNDED_TYPES = [
        'isa_topup_vs_psa', 'pension_tax_relief', 'non_earner_spouse_pension', 'junior_isa', 'lifetime_isa',
    ];
}
